<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The checkout form: contact details, the pharmacist's questions and how to pay.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{9,20}$/'],
            'taker' => ['required', Rule::in(['me', 'other'])],
            'age' => ['required', 'integer', 'between:0,120'],
            'pregnant' => ['required', Rule::in(['no', 'yes', 'na'])],
            'health_notes' => ['nullable', 'string', 'max:1000'],
            'allergies' => ['nullable', 'string', 'max:255'],
            'consent' => ['accepted'],
            'payment' => ['required', Rule::in(['card', 'counter'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Write a phone number, for example 600 000 000.',
            'consent.accepted' => 'Tick the box so the pharmacist can use your answers to check the order.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'taker' => 'who will take them',
            'pregnant' => 'pregnant or breastfeeding',
            'health_notes' => 'other medicines or health problems',
            'payment' => 'how to pay',
        ];
    }
}
