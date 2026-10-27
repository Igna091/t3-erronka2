<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The CSRF token: a change is accepted only with the token of the user's own session.
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTheTokenIsRandomAndStaysTheSameForTheSession(): void
    {
        $token = csrf_token();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertSame($token, csrf_token());
    }

    public function testOnlyTheSessionsTokenIsValid(): void
    {
        $token = csrf_token();
        $forged = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');

        self::assertTrue(csrf_valid($token));
        self::assertFalse(csrf_valid($forged));
        self::assertFalse(csrf_valid(''));
        self::assertFalse(csrf_valid(null));
        self::assertFalse(csrf_valid([$token]));
    }

    public function testWithoutATokenInTheSessionNothingIsValid(): void
    {
        self::assertFalse(csrf_valid(''));
        self::assertFalse(csrf_valid('anything'));
    }

    public function testTheFieldCarriesTheToken(): void
    {
        self::assertSame('<input type="hidden" name="csrf" value="' . csrf_token() . '">', csrf_field());
    }
}
