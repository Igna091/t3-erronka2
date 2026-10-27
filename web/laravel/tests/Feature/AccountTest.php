<?php

namespace Tests\Feature;

use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\AccountEvent;
use App\Models\KnownDevice;
use App\Models\Order;
use App\Models\OutboxEmail;
use App\Models\User;
use Database\Seeders\MedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private const CAPTCHA = 'pills';

    public function test_guests_see_log_in_and_sign_up_and_the_forms_load(): void
    {
        $this->get('/')->assertOk()->assertSee('Log in')->assertSee('Sign up')->assertDontSee('Log out');

        foreach (['/login', '/register', '/forgot-password'] as $page) {
            $this->assertCspSafe($this->get($page)->assertOk()->getContent());
        }
        $this->get('/register')->assertSee('Type the characters in the picture');
    }

    public function test_the_captcha_picture_is_a_fresh_png_each_time(): void
    {
        $response = $this->get('/captcha')->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith("\x89PNG", (string) $response->getContent());
        $this->assertIsArray(session('captcha'));
    }

    public function test_sign_up_opens_an_unconfirmed_account_and_queues_the_confirmation_email(): void
    {
        $this->withSession($this->captcha())->post('/register', $this->signUp())->assertRedirect('/account');

        $user = User::where('email', 'ane@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->password_changed_at);
        $this->get('/account')->assertRedirect('/email/verify');
        $this->assertCspSafe($this->get('/email/verify')->assertSee('Check your inbox')->getContent());

        $email = OutboxEmail::where('kind', 'verify_email')->sole();
        $this->assertSame($user->id, $email->user_id);
        $this->assertNull($email->order_id);
        $payload = (array) $email->payload;
        $this->assertSame(['name' => 'Ane Etxeberria', 'email' => 'ane@example.com'], $payload['to']);
        $this->assertStringContainsString('/email/verify/'.$user->id.'/', $payload['url']);
        $this->assertStringContainsString('signature=', $payload['url']);
        $this->assertSame(60, $payload['expires_minutes']);
    }

    public function test_sign_up_refuses_short_and_leaked_passwords_a_wrong_picture_and_no_consent(): void
    {
        $this->withSession($this->captcha())->post('/register', $this->signUp(['password' => 'tooshort']))
            ->assertSessionHasErrors('password');
        $this->withSession($this->captcha())->post('/register', $this->signUp(['password' => 'Password1234']))
            ->assertSessionHasErrors(['password' => 'That password appears in lists of leaked passwords, so attackers try it first. Choose another one.']);
        $this->withSession($this->captcha())->post('/register', $this->signUp(['captcha' => 'wrong']))
            ->assertSessionHasErrors('captcha');
        $this->withSession($this->captcha())->post('/register', $this->signUp(['privacy' => null]))
            ->assertSessionHasErrors('privacy');
        // A picture works once: the second try with the same answer fails
        $this->post('/register', $this->signUp())->assertSessionHasErrors('captcha');

        $this->assertSame(0, User::count());
    }

    public function test_the_confirmation_link_confirms_the_email_and_offers_two_step(): void
    {
        $user = User::factory()->unverified()->create();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($link)->assertRedirect('/account/security/two-step');

        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->get('/account/security/two-step')->assertSee('Your email is confirmed.')->assertSee('Protect your account');
    }

    public function test_logging_in_records_it_and_remembers_the_browser_without_an_alert(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => 'Ane@Example.com', 'password' => 'a-long-enough-pass'])->assertRedirect('/account');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['login'], $user->accountEvents()->pluck('type')->all());
        $this->assertSame(1, $user->knownDevices()->count());
        $this->assertSame(0, OutboxEmail::where('kind', 'security_alert')->count());
    }

    public function test_a_wrong_password_and_an_unknown_email_get_the_same_answer(): void
    {
        $user = $this->user();
        $message = trans('auth.failed');

        $this->post('/login', ['email' => 'ane@example.com', 'password' => 'not-the-password'])->assertSessionHasErrors(['email' => $message]);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'not-the-password'])->assertSessionHasErrors(['email' => $message]);

        $this->assertGuest();
        $this->assertSame(['login_failed'], $user->accountEvents()->pluck('type')->all());
    }

    public function test_three_failed_logins_bring_the_picture_check(): void
    {
        $this->user();
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => 'ane@example.com', 'password' => 'not-the-password']);
        }

        $this->get('/login')->assertSee('Type the characters in the picture');
        $this->post('/login', ['email' => 'ane@example.com', 'password' => 'a-long-enough-pass'])->assertSessionHasErrors('captcha');
        $this->assertGuest();

        $this->withSession($this->captcha())
            ->post('/login', ['email' => 'ane@example.com', 'password' => 'a-long-enough-pass', 'captcha' => self::CAPTCHA])
            ->assertRedirect('/account');
        $this->assertAuthenticated();
    }

    public function test_too_many_attempts_lock_the_form_with_a_message(): void
    {
        $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->withSession($this->captcha())
                ->post('/login', ['email' => 'ane@example.com', 'password' => 'not-the-password', 'captcha' => self::CAPTCHA]);
        }

        $this->withSession($this->captcha())
            ->post('/login', ['email' => 'ane@example.com', 'password' => 'a-long-enough-pass', 'captcha' => self::CAPTCHA])
            ->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many attempts.', (string) session('errors')?->first('email'));
        $this->assertGuest();
    }

    public function test_a_login_from_a_new_browser_sends_an_alert_but_a_known_one_does_not(): void
    {
        $user = $this->user();
        $user->knownDevices()->create(['token_hash' => hash('sha256', 'known-browser'), 'user_agent' => 'Firefox']);

        $this->withCookie(KnownDevice::COOKIE, 'known-browser')
            ->post('/login', ['email' => 'ane@example.com', 'password' => 'a-long-enough-pass']);
        $this->assertSame(0, OutboxEmail::where('kind', 'security_alert')->count());
        $this->post('/logout');

        // Another browser: without the cookie
        $this->defaultCookies = [];
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1')
            ->post('/login', ['email' => 'ane@example.com', 'password' => 'a-long-enough-pass'])
            ->assertCookie(KnownDevice::COOKIE);

        $alert = (array) OutboxEmail::where('kind', 'security_alert')->sole()->payload;
        $this->assertSame('new_device_login', $alert['event']);
        $this->assertSame('Safari on iPhone', $alert['device']);
    }

    public function test_forgot_password_answers_the_same_whether_or_not_the_email_has_an_account(): void
    {
        $this->user();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);
        $this->post('/forgot-password', ['email' => 'ane@example.com'])->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);
        // Asked again within the minute: throttled, and still the same answer
        $this->post('/forgot-password', ['email' => 'ane@example.com'])->assertSessionHas('status', PasswordResetLinkResponse::MESSAGE);

        $this->assertSame(1, OutboxEmail::where('kind', 'reset_password')->count());
    }

    public function test_reset_links_use_the_site_address_not_the_requests_host(): void
    {
        $this->user();

        $this->post('http://evil.example/forgot-password', ['email' => 'ane@example.com']);

        $payload = (array) OutboxEmail::where('kind', 'reset_password')->sole()->payload;
        $this->assertStringStartsWith(config('app.url').'/reset-password/', $payload['url']);
        $this->assertStringContainsString('email=ane%40example.com', $payload['url']);
    }

    public function test_a_password_reset_changes_it_logs_out_everywhere_and_alerts(): void
    {
        $user = $this->user();
        DB::table('sessions')->insert(['id' => 'other-browser', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $token = Password::createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => 'ane@example.com', 'password' => 'a brand new long password'])
            ->assertRedirect('/login');

        $user->refresh();
        $this->assertTrue(Hash::check('a brand new long password', $user->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame('password_reset', ((array) OutboxEmail::where('kind', 'security_alert')->sole()->payload)['event']);
        $this->assertTrue($user->accountEvents()->where('type', 'password_reset')->exists());
    }

    public function test_changing_the_password_needs_the_current_one_and_alerts(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/user/password', ['current_password' => 'wrong', 'password' => 'a brand new long password'])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->actingAs($user)->put('/user/password', ['current_password' => 'a-long-enough-pass', 'password' => 'a brand new long password'])
            ->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('a brand new long password', (string) $user->fresh()?->password));
        $this->assertSame('password_changed', ((array) OutboxEmail::where('kind', 'security_alert')->sole()->payload)['event']);
    }

    public function test_account_pages_need_a_login_and_a_confirmed_email(): void
    {
        $this->get('/account')->assertRedirect('/login');
        $this->get('/account/security')->assertRedirect('/login');

        $this->actingAs(User::factory()->unverified()->create())->get('/account/profile')->assertRedirect('/email/verify');
    }

    public function test_the_dashboard_shows_the_accounts_orders_and_what_to_do_next(): void
    {
        $user = $this->user();
        $order = $this->orderFor($user);

        $response = $this->actingAs($user)->get('/account')->assertOk()
            ->assertSee('Hello, Ane')
            ->assertSee($order->reference)
            ->assertSee('Add a profile photo')
            ->assertSee('Turn on 2-step verification');
        $this->assertCspSafe($response->getContent());
    }

    public function test_the_header_and_landing_page_greet_a_logged_in_user(): void
    {
        $user = $this->user();
        $this->orderFor($user);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('Your account menu')
            ->assertSee('Hello, Ane.')
            ->assertSee('is being prepared')
            ->assertDontSee('Sign up');
    }

    public function test_orders_placed_while_logged_in_belong_to_the_account(): void
    {
        $this->seed(MedicineSeeder::class);
        $user = $this->user();

        $this->actingAs($user)->post('/basket/paracetamol-500-mg');
        $this->get('/checkout')->assertSee('value="ane@example.com"', false);
        $this->post('/checkout', [
            'name' => 'Ane Etxeberria', 'email' => 'ane@example.com', 'phone' => '600 000 000', 'taker' => 'me',
            'age' => 34, 'pregnant' => 'no', 'consent' => '1', 'payment' => 'counter',
        ]);
        $order = Order::sole();
        $this->assertSame($user->id, $order->user_id);

        // Another browser of the same account sees it; another account doesn't
        $this->flushSession();
        $this->actingAs($user)->get("/orders/{$order->reference}")->assertOk();
        $this->actingAs(User::factory()->create())->get("/orders/{$order->reference}")->assertNotFound();
    }

    public function test_the_security_page_lists_browsers_that_can_be_logged_out(): void
    {
        $user = $this->user();
        foreach (['browser-one', 'browser-two'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '10.20.30.40', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Firefox/140.0', 'payload' => '', 'last_activity' => time() - 3600]);
        }
        AccountEvent::record($user, 'login_failed');

        $response = $this->actingAs($user)->get('/account/security')->assertOk()
            ->assertSee('Firefox on Windows')
            ->assertSee('Wrong password')
            ->assertDontSee('browser-one');
        $this->assertCspSafe($response->getContent());

        $this->delete('/account/sessions/'.hash('sha256', 'browser-one'))->assertRedirect('/account/security');
        $this->assertSame(['browser-two'], DB::table('sessions')->pluck('id')->all());

        $this->delete('/account/sessions');
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertTrue($user->accountEvents()->where('type', 'sessions_ended')->exists());
    }

    public function test_a_profile_photo_is_redrawn_and_shown_only_to_its_owner(): void
    {
        Storage::fake('local');
        $user = $this->user();

        $this->actingAs($user)->post('/account/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 900, 600)])
            ->assertRedirect('/account/profile');

        $path = (string) $user->fresh()?->profile_image;
        $bytes = (string) Storage::disk('local')->get($path);
        $this->assertStringStartsWith('profile-photos/', $path);
        $this->assertSame('WEBP', substr($bytes, 8, 4));
        $this->assertSame([512, 512], array_slice((array) getimagesizefromstring($bytes), 0, 2));

        $this->get('/account/profile/photo')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertCspSafe($this->get('/account/profile')->getContent());
        $this->actingAs(User::factory()->create())->get('/account/profile/photo')->assertNotFound();

        $this->actingAs($user)->post('/account/profile/photo', ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->delete('/account/profile/photo');
        $this->assertNull($user->fresh()?->profile_image);
        Storage::disk('local')->assertMissing($path);
    }

    private function user(): User
    {
        return User::factory()->create([
            'name' => 'Ane Etxeberria',
            'email' => 'ane@example.com',
            'password' => Hash::make('a-long-enough-pass'),
        ]);
    }

    private function orderFor(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id, 'reference' => Order::newReference(), 'customer_name' => $user->name,
            'email' => $user->email, 'phone' => '600 000 000', 'taker' => 'me', 'age' => '34', 'pregnant' => 'no',
            'health_consent_at' => now(), 'payment_method' => 'counter', 'payment_status' => 'at_counter',
            'total_cents' => 195, 'status' => 'received',
        ]);
    }

    /**
     * A picture whose answer is self::CAPTCHA, as /captcha would leave it in the session.
     *
     * @return array<string, mixed>
     */
    private function captcha(): array
    {
        return ['captcha' => ['hash' => hash('sha256', self::CAPTCHA), 'expires' => time() + 600]];
    }

    /**
     * @param  array<string, mixed>  $overrides  null removes a field
     * @return array<string, mixed>
     */
    private function signUp(array $overrides = []): array
    {
        return array_filter(array_merge([
            'name' => 'Ane Etxeberria',
            'email' => 'Ane@Example.com',
            'password' => 'blue capsule at nine',
            'privacy' => '1',
            'captcha' => self::CAPTCHA,
        ], $overrides), fn ($value) => $value !== null);
    }
}
