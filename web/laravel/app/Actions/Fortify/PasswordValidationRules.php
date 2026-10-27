<?php

namespace App\Actions\Fortify;

use App\Rules\NotLeakedPassword;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;

trait PasswordValidationRules
{
    /**
     * At least 12 characters and not a leaked password, with no rules about symbols or capitals
     * (OWASP ASVS 2.1). There's no "repeat the password" field: the form has a Show button.
     *
     * @return array<int, Rule|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:12', 'max:128', new NotLeakedPassword];
    }
}
