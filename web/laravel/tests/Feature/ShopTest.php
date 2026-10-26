<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\OutboxEmail;
use Database\Seeders\MedicineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MedicineSeeder::class);
    }

    public function test_catalogue_lists_every_medicine(): void
    {
        $this->get('/shop')
            ->assertOk()
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('Chlorhexidine 10 mg/ml')
            ->assertSee('€1.95');
    }

    public function test_catalogue_filters_by_category_and_ignores_unknown_ones(): void
    {
        $this->get('/shop?category=stomach')->assertOk()->assertSee('Omeprazole 20 mg')->assertDontSee('Paracetamol 500 mg');
        $this->get('/shop?category=nonsense')->assertOk()->assertSee('Paracetamol 500 mg');
        $this->get('/shop?category[]=pain')->assertOk()->assertSee('Omeprazole 20 mg');
    }

    public function test_medicine_page(): void
    {
        $this->get('/shop/paracetamol-500-mg')
            ->assertOk()
            ->assertSee('Read the instructions of this medicine and ask your pharmacist.');
        $this->get('/shop/amoxicillin-500-mg')->assertNotFound();
    }

    public function test_a_medicine_taken_off_sale_cannot_be_bought(): void
    {
        Medicine::where('slug', 'ibuprofen-200-mg')->update(['active' => false]);

        $this->get('/shop/ibuprofen-200-mg')->assertNotFound();
        $this->post('/basket/ibuprofen-200-mg')->assertNotFound();
    }

    public function test_basket_takes_at_most_two_packs_of_a_medicine(): void
    {
        $paracetamol = Medicine::where('slug', 'paracetamol-500-mg')->firstOrFail();

        $this->post('/basket/paracetamol-500-mg');
        $this->post('/basket/paracetamol-500-mg');
        $this->post('/basket/paracetamol-500-mg')->assertSessionHas('status', fn (string $status) => str_contains($status, 'the most per order'));
        $this->post('/basket/omeprazole-20-mg', ['quantity' => 5])->assertSessionHasErrors('quantity');

        $this->assertSame([$paracetamol->id => 2], session('basket'));
        $this->get('/shop')->assertSee('Limit reached');
    }

    public function test_basket_quantities_can_be_changed_and_removed(): void
    {
        $this->post('/basket/paracetamol-500-mg');
        $this->patch('/basket/paracetamol-500-mg', ['quantity' => 2])->assertRedirect('/basket');
        $this->get('/basket')->assertSee('€3.90');

        $this->patch('/basket/paracetamol-500-mg', ['quantity' => 3])->assertSessionHasErrors('quantity');
        $this->delete('/basket/paracetamol-500-mg');
        $this->get('/basket')->assertSee('Your basket is empty.');
    }

    public function test_checkout_needs_a_basket(): void
    {
        $this->get('/checkout')->assertRedirect('/basket');
        $this->post('/checkout', $this->answers())->assertRedirect('/basket');
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_needs_the_pharmacists_consent_box(): void
    {
        $this->post('/basket/paracetamol-500-mg');

        $this->post('/checkout', $this->answers(['consent' => null]))->assertSessionHasErrors('consent');
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_places_the_order_and_keeps_health_answers_encrypted(): void
    {
        $this->post('/basket/paracetamol-500-mg');
        $this->post('/basket/omeprazole-20-mg');

        $response = $this->post('/checkout', $this->answers());

        $order = Order::with('items')->firstOrFail();
        $response->assertRedirect("/orders/{$order->reference}/pay");
        $this->assertSame(195 + 495, $order->total_cents);
        $this->assertCount(2, $order->items);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame([], session('basket', []));

        $this->assertSame('Penicillin', $order->allergies);
        $this->assertNotSame('Penicillin', DB::table('orders')->value('allergies'));
        $this->assertNotSame('34', DB::table('orders')->value('age'));
    }

    public function test_paying_at_the_counter_goes_straight_to_the_order(): void
    {
        $order = $this->placeOrder(['payment' => 'counter']);

        $this->assertSame('at_counter', $order->payment_status);
        $this->get("/orders/{$order->reference}")->assertOk()->assertSee('Order received')->assertSee('At the counter');
    }

    public function test_card_payment_refuses_any_card_that_is_not_a_demo_card(): void
    {
        $order = $this->placeOrder();

        $this->post("/orders/{$order->reference}/pay", $this->card('5555 5555 5555 4444'))
            ->assertSessionHasErrors(['card_number' => 'In this demo only the demo cards work, so a real card is never used. Pick one under the card number.']);

        $this->assertSame('pending', $order->refresh()->payment_status);
        $this->assertFalse(session()->hasOldInput('card_number'));
        $this->assertFalse(session()->hasOldInput('card_cvc'));
    }

    public function test_card_payment_refuses_an_expired_card(): void
    {
        $order = $this->placeOrder();

        $this->post("/orders/{$order->reference}/pay", $this->card('4242 4242 4242 4242', '01/20'))
            ->assertSessionHasErrors('card_expiry');
    }

    public function test_the_declined_demo_card_is_declined(): void
    {
        $order = $this->placeOrder();

        $this->post("/orders/{$order->reference}/pay", $this->card('4000 0000 0000 0002'))->assertRedirect("/orders/{$order->reference}/pay");

        $this->assertSame('declined', $order->refresh()->payment_status);
        $this->get("/orders/{$order->reference}/pay")->assertSee('This demo card is always declined');
    }

    public function test_the_approved_demo_card_needs_the_banks_code_and_only_its_last_digits_are_kept(): void
    {
        $order = $this->placeOrder();

        $this->post("/orders/{$order->reference}/pay", $this->card('4242 4242 4242 4242'));
        $code = session("payment_check.{$order->id}.code");
        $this->get("/orders/{$order->reference}/pay")->assertSee($code)->assertSee('Confirm it’s you');

        $this->post("/orders/{$order->reference}/confirm", ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertSame('pending', $order->refresh()->payment_status);

        $this->post("/orders/{$order->reference}/confirm", ['code' => $code])->assertRedirect("/orders/{$order->reference}/pay");
        $this->get("/orders/{$order->reference}/pay")->assertSee('Payment approved');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('4242', $order->card_last4);
        $this->assertStringNotContainsString('4242424242424242', DB::table('orders')->get()->toJson());
        $this->assertStringNotContainsString('4242424242424242', (string) json_encode(session()->all()));
    }

    public function test_three_wrong_codes_decline_the_payment(): void
    {
        $order = $this->placeOrder();
        $this->post("/orders/{$order->reference}/pay", $this->card('4242 4242 4242 4242'));

        $this->post("/orders/{$order->reference}/confirm", ['code' => '000000']);
        $this->post("/orders/{$order->reference}/confirm", ['code' => '000000']);
        $this->post("/orders/{$order->reference}/confirm", ['code' => '000000']);

        $this->assertSame('declined', $order->refresh()->payment_status);
        $this->get("/orders/{$order->reference}/pay")->assertSee('The code was wrong three times');
    }

    public function test_a_paid_order_cannot_be_paid_again(): void
    {
        $order = $this->placeOrder();
        $this->post("/orders/{$order->reference}/pay", $this->card('4242 4242 4242 4242'));
        $this->post("/orders/{$order->reference}/confirm", ['code' => session("payment_check.{$order->id}.code")]);

        $this->post("/orders/{$order->reference}/pay", $this->card('4000 0000 0000 0002'))->assertRedirect("/orders/{$order->reference}");
        $this->assertSame('paid', $order->refresh()->payment_status);
    }

    public function test_an_order_is_private_to_the_session_that_placed_it(): void
    {
        $order = $this->placeOrder();
        $this->get("/orders/{$order->reference}")->assertOk();

        $this->flushSession();

        $this->get("/orders/{$order->reference}")->assertNotFound();
        $this->get("/orders/{$order->reference}/pay")->assertNotFound();
        $this->post("/orders/{$order->reference}/pay", $this->card('4242 4242 4242 4242'))->assertNotFound();
        $this->post("/orders/{$order->reference}/pay-at-counter")->assertNotFound();
    }

    public function test_paying_at_the_counter_queues_the_confirmation_email_without_health_answers(): void
    {
        $order = $this->placeOrder(['payment' => 'counter', 'health_notes' => 'Takes warfarin']);

        $email = OutboxEmail::sole();
        $this->assertSame($order->id, $email->order_id);
        $this->assertSame('order_confirmation', $email->kind);
        $this->assertNull($email->sent_at);

        $payload = $this->queuedEmail();
        $this->assertSame(['name' => 'Ane Etxeberria', 'email' => 'ane@example.com'], $payload['to']);
        $this->assertSame($order->reference, $payload['reference']);
        $this->assertSame([['name' => 'Paracetamol 500 mg', 'quantity' => 1, 'line_cents' => 195]], $payload['items']);
        $this->assertSame(195, $payload['total_cents']);
        $this->assertSame(['status' => 'at_counter', 'card_last4' => null], $payload['payment']);

        $stored = (string) DB::table('email_outbox')->value('payload');
        foreach (['Penicillin', 'warfarin', 'pregnant', 'taker', '"age"'] as $healthAnswer) {
            $this->assertStringNotContainsString($healthAnswer, $stored);
        }
    }

    public function test_a_card_order_queues_its_confirmation_email_once_the_payment_is_approved(): void
    {
        $order = $this->placeOrder();
        $this->post("/orders/{$order->reference}/pay", $this->card('4242 4242 4242 4242'));
        $this->assertSame(0, OutboxEmail::count());

        $this->post("/orders/{$order->reference}/confirm", ['code' => session("payment_check.{$order->id}.code")]);

        $this->assertSame(['status' => 'paid', 'card_last4' => '4242'], $this->queuedEmail()['payment']);
    }

    public function test_a_declined_card_queues_nothing_until_the_order_is_paid_at_the_counter(): void
    {
        $order = $this->placeOrder();
        $this->post("/orders/{$order->reference}/pay", $this->card('4000 0000 0000 0002'));
        $this->assertSame(0, OutboxEmail::count());

        $this->post("/orders/{$order->reference}/pay-at-counter");
        $this->post("/orders/{$order->reference}/pay-at-counter");

        $this->assertSame(['status' => 'at_counter', 'card_last4' => null], $this->queuedEmail()['payment']);
    }

    public function test_shop_pages_use_nothing_the_content_security_policy_blocks(): void
    {
        $this->assertCspSafe($this->get('/shop')->getContent());
        $this->assertCspSafe($this->get('/shop/ambroxol-15-mg-5-ml')->getContent());

        $this->post('/basket/paracetamol-500-mg');
        $this->assertCspSafe($this->get('/basket')->getContent());
        $this->assertCspSafe($this->get('/checkout')->getContent());

        $order = $this->placeOrder();
        $this->assertCspSafe($this->get("/orders/{$order->reference}/pay")->getContent());
        $this->post("/orders/{$order->reference}/pay", $this->card('4242 4242 4242 4242'));
        $this->assertCspSafe($this->get("/orders/{$order->reference}/pay")->getContent());
        $this->assertCspSafe($this->get("/orders/{$order->reference}")->getContent());
    }

    /**
     * @param  array<string, mixed>  $overrides  null removes a field
     * @return array<string, mixed>
     */
    private function answers(array $overrides = []): array
    {
        return array_filter(array_merge([
            'name' => 'Ane Etxeberria',
            'email' => 'ane@example.com',
            'phone' => '600 000 000',
            'taker' => 'me',
            'age' => 34,
            'pregnant' => 'no',
            'allergies' => 'Penicillin',
            'consent' => '1',
            'payment' => 'card',
        ], $overrides), fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function placeOrder(array $answers = []): Order
    {
        $this->post('/basket/paracetamol-500-mg');
        $this->post('/checkout', $this->answers($answers));

        return Order::latest('id')->firstOrFail();
    }

    /**
     * The payload of the only email queued so far.
     *
     * @return array<string, mixed>
     */
    private function queuedEmail(): array
    {
        $payload = OutboxEmail::sole()->payload;
        if ($payload === null) {
            $this->fail('The queued email has no payload');
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function card(string $number, string $expiry = '12/30'): array
    {
        return ['card_name' => 'Test Client', 'card_number' => $number, 'card_expiry' => $expiry, 'card_cvc' => '123'];
    }
}
