<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the user's name and email. A new email has to be confirmed again.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ])->validateWithBag('updateProfileInformation');

        $email = Str::lower(trim($input['email']));
        $user->forceFill(['name' => trim($input['name'])]);

        if ($email !== $user->email) {
            $user->forceFill(['email' => $email, 'email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();

            return;
        }

        $user->save();
    }
}
