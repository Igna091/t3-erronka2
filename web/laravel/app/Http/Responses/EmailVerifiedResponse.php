<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse;

/**
 * After the link in the "confirm your email" message: the offer to turn on 2-step verification,
 * unless it's on already.
 */
final class EmailVerifiedResponse implements VerifyEmailResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();
        $next = $user instanceof User && ! $user->hasTwoStep() ? 'account.two-step' : 'account.dashboard';

        return redirect()->route($next)->with('status', 'Your email is confirmed.');
    }
}
