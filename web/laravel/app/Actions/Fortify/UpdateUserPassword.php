<?php

namespace App\Actions\Fortify;

use App\Models\AccountEvent;
use App\Models\User;
use App\Support\AccountEmails;
use App\Support\UserSessions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password, then log out every other browser of the account
     * and email an alert.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => [...$this->passwordRules(), 'different:current_password'],
        ], [
            'current_password.current_password' => 'That isn’t your current password.',
            'password.different' => 'Choose a password different from the current one.',
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'password_changed_at' => now(),
        ])->save();

        UserSessions::endOthers($user, session()->getId());
        AccountEvent::record($user, 'password_changed');
        AccountEmails::securityAlert($user, 'password_changed');
    }
}
