<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\Captcha;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user. The email address is confirmed next
     * (Registered event, then User::sendEmailVerificationNotification()).
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'privacy' => ['accepted'],
            'captcha' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! Captcha::passes(session()->driver(), $value)) {
                    $fail('The characters don’t match the picture. Type the ones in the new picture.');
                }
            }],
        ], [
            'privacy.accepted' => 'Tick the box to say you’ve read the privacy notice.',
            'captcha.required' => 'Type the characters in the picture.',
            'email.unique' => 'There’s already an account with that email. Log in, or reset the password.',
        ])->validate();

        $user = new User([
            'name' => trim($input['name']),
            'email' => Str::lower(trim($input['email'])),
            'password' => Hash::make($input['password']),
        ]);
        $user->password_changed_at = now();
        $user->save();

        return $user;
    }
}
