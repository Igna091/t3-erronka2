<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The simulated card form. Only the demo card numbers in config/shop.php pass, so a real card
 * is never accepted, and none of these fields is flashed back to the session (bootstrap/app.php).
 */
class CardPaymentRequest extends FormRequest
{
    /**
     * Only the session that placed the order may pay for it.
     */
    public function authorize(): bool
    {
        return $this->route('order')->isOwnedBy($this->session());
    }

    /**
     * Someone else's order looks the same as one that doesn't exist.
     */
    protected function failedAuthorization(): void
    {
        abort(404);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_number' => preg_replace('/\D/', '', (string) $this->input('card_number')),
            'card_cvc' => preg_replace('/\D/', '', (string) $this->input('card_cvc')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'card_name' => ['required', 'string', 'max:60'],
            'card_number' => ['bail', 'required', 'digits:16', Rule::in(array_keys(config('shop.demo_cards')))],
            'card_expiry' => ['bail', 'required', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', $this->notExpired(...)],
            'card_cvc' => ['required', 'digits:3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_number.digits' => 'A card number has 16 digits.',
            'card_number.in' => 'In this demo only the demo cards work, so a real card is never used. Pick one under the card number.',
            'card_expiry.regex' => 'Write the expiry date as MM/YY, for example 12/30.',
            'card_cvc.digits' => 'The security code is the 3 digits on the back of the card.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'card_name' => 'name on the card',
            'card_number' => 'card number',
            'card_expiry' => 'expiry date',
            'card_cvc' => 'security code',
        ];
    }

    private function notExpired(string $attribute, string $value, Closure $fail): void
    {
        [$month, $year] = explode('/', $value);

        if (Carbon::create(2000 + (int) $year, (int) $month)->endOfMonth()->isPast()) {
            $fail('That card has expired.');
        }
    }
}
