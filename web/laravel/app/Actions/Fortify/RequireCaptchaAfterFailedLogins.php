<?php

namespace App\Actions\Fortify;

use App\Support\Captcha;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * A step of the login pipeline (FortifyServiceProvider): after a few failed logins from this
 * address or on this email, the picture has to be right before the password is even checked.
 */
class RequireCaptchaAfterFailedLogins
{
    public function handle(Request $request, Closure $next): mixed
    {
        $email = (string) $request->input(Fortify::username());

        if (Captcha::requiredForLogin($request, $email) && ! Captcha::passes($request->session(), $request->input('captcha'))) {
            throw ValidationException::withMessages([
                'captcha' => 'Type the characters in the picture, then your password again.',
            ]);
        }

        return $next($request);
    }
}
