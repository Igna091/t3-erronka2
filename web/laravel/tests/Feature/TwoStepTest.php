<?php

namespace Tests\Feature;

use App\Models\OutboxEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoStepTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public function test_turning_it_on_takes_the_password_a_code_and_saving_the_recovery_codes(): void
    {
        $user = $this->user();
        $this->actingAs($user);

        $this->get('/account/security/two-step')->assertOk()->assertSee('Protect your account with 2-step verification');
        $this->get('/account/security/two-step?step=password')->assertSee('Confirm it’s you');
        $this->post('/account/security/two-step', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/account/security/two-step', ['password' => 'a-long-enough-pass'])->assertRedirect('/account/security/two-step');

        $scan = $this->get('/account/security/two-step')->assertOk()->assertSee('Add your account to the app')->assertSee('<svg', false);
        $this->assertCspSafe($scan->getContent());
        $secret = $this->secretOf($user);
        $this->assertFalse($user->refresh()->hasTwoStep());

        $this->post('/account/security/two-step/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/account/security/two-step/confirm', ['code' => $this->codeFor($secret)])->assertRedirect('/account/security/two-step');

        $codes = $user->refresh()->recoveryCodes();
        $this->assertCount(8, $codes);
        $this->get('/account/security/two-step')->assertSee('Save your recovery codes')->assertSee($codes[0]);
        $this->post('/account/security/two-step/finish')->assertSessionHasErrors('saved');
        $this->post('/account/security/two-step/finish', ['saved' => '1']);
        $this->get('/account/security/two-step')->assertSee('2-step verification is on');

        $this->assertTrue($user->refresh()->hasTwoStep());
        $this->assertSame('two_factor_enabled', ((array) OutboxEmail::where('kind', 'security_alert')->sole()->payload)['event']);
        $this->get('/account/security')->assertSee('8 of 8 left');
    }

    public function test_cancelling_the_setup_forgets_the_unconfirmed_secret(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post('/account/security/two-step');
        $this->assertNotNull($user->refresh()->two_factor_secret);

        $this->delete('/account/security/two-step/setup')->assertRedirect('/account/security');

        $this->assertNull($user->refresh()->two_factor_secret);
        $this->assertSame(0, OutboxEmail::count());
    }

    public function test_logging_in_asks_for_the_code_from_the_app(): void
    {
        $user = $this->userWithTwoStep();

        $this->post('/login', ['email' => 'ane@example.com', 'password' => 'a-long-enough-pass'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->assertCspSafe($this->get('/two-factor-challenge')->assertSee('Enter your code')->getContent());

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['code' => $this->codeFor(self::SECRET)])->assertRedirect('/account');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(['two_step_failed', 'login_two_step'], $user->accountEvents()->orderBy('id')->pluck('type')->all());
    }

    public function test_a_recovery_code_logs_in_once_and_sends_an_alert(): void
    {
        $user = $this->userWithTwoStep();
        $this->post('/login', ['email' => 'ane@example.com', 'password' => 'a-long-enough-pass']);

        $this->get('/two-factor-challenge?recovery=1')->assertSee('Recovery code');
        $this->post('/two-factor-challenge', ['recovery_code' => 'first-recovery-code'])->assertRedirect('/account');

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains('first-recovery-code', $user->refresh()->recoveryCodes());
        $this->assertSame('recovery_code_used', ((array) OutboxEmail::where('kind', 'security_alert')->sole()->payload)['event']);
    }

    public function test_turning_it_off_takes_the_password_and_a_code(): void
    {
        $user = $this->userWithTwoStep();
        $this->actingAs($user);

        // Without the password in the last 15 minutes, to the password page and back to a page
        $this->delete('/user/two-factor-authentication', ['code' => $this->codeFor(self::SECRET)])->assertRedirect('/user/confirm-password');
        $this->assertSame(route('account.two-step.turn-off'), session('url.intended'));
        $this->assertTrue($user->refresh()->hasTwoStep());

        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->assertCspSafe($this->get('/account/security/two-step/turn-off')->assertOk()->getContent());
        $this->delete('/user/two-factor-authentication', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertTrue($user->refresh()->hasTwoStep());

        $this->delete('/user/two-factor-authentication', ['code' => 'second-recovery-code'])->assertRedirect('/account/security');
        $this->assertFalse($user->refresh()->hasTwoStep());
        $this->assertSame('two_factor_disabled', ((array) OutboxEmail::where('kind', 'security_alert')->sole()->payload)['event']);
    }

    public function test_the_recovery_codes_page_asks_for_the_password_and_makes_new_codes(): void
    {
        $user = $this->userWithTwoStep();
        $this->actingAs($user);

        $this->get('/account/security/recovery-codes')->assertRedirect('/user/confirm-password');
        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->assertCspSafe($this->get('/account/security/recovery-codes')->assertSee('first-recovery-code')->getContent());

        $this->post('/account/security/recovery-codes')->assertRedirect('/account/security/recovery-codes');

        $codes = $user->refresh()->recoveryCodes();
        $this->assertCount(8, $codes);
        $this->assertNotContains('first-recovery-code', $codes);
        $this->assertTrue($user->accountEvents()->where('type', 'recovery_codes_regenerated')->exists());
    }

    private function user(): User
    {
        return User::factory()->create([
            'name' => 'Ane Etxeberria',
            'email' => 'ane@example.com',
            'password' => Hash::make('a-long-enough-pass'),
        ]);
    }

    private function userWithTwoStep(): User
    {
        $user = $this->user();
        $codes = ['first-recovery-code', 'second-recovery-code', 'c3', 'c4', 'c5', 'c6', 'c7', 'c8'];
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(self::SECRET),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode($codes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function secretOf(User $user): string
    {
        return Fortify::currentEncrypter()->decrypt((string) $user->refresh()->two_factor_secret);
    }

    private function codeFor(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }
}
