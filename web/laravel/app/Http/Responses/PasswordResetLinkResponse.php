<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * The answer to "forgot your password", sent or not: an unknown email, or one asked for a minute
 * ago, looks exactly like a link on its way.
 */
final class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public const MESSAGE = 'If there’s an account with that email, we’ve sent it a link to choose a new password. It works for 60 minutes.';

    /**
     * Fortify passes the password broker's status, which this response never reveals.
     */
    public function __construct(string $status = '') {} // @phpstan-ignore constructor.unusedParameter

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => self::MESSAGE])
            : back()->with('status', self::MESSAGE);
    }
}
