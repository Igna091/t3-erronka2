<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Passwords are stored as Argon2id hashes, and guessing them is slowed down.
 */
final class AuthTest extends TestCase
{
    public function testPasswordsAreHashedWithArgon2id(): void
    {
        $hash = hash_password('blue capsule at nine');

        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue(password_verify('blue capsule at nine', $hash));
        self::assertFalse(password_verify('blue capsule at ten', $hash));
    }

    public function testTheSamePasswordGetsADifferentHashEachTime(): void
    {
        // Salted: two users with the same password don't share a hash, unlike the original's MD5
        self::assertNotSame(hash_password('blue capsule at nine'), hash_password('blue capsule at nine'));
    }

    public function testTheSeededAccountsHaveNoPasswordThatWorks(): void
    {
        // db/init/01-bdweb.sql stores '!', which is no hash at all
        self::assertFalse(password_verify('', '!'));
        self::assertFalse(password_verify('password', '!'));
    }

    public function testLoginsAreThrottledPerAccountAndPerIp(): void
    {
        self::assertFalse(login_throttled(0, 0));
        self::assertFalse(login_throttled(LOGIN_MAX_FAILURES_PER_ACCOUNT - 1, LOGIN_MAX_FAILURES_PER_IP - 1));
        self::assertTrue(login_throttled(LOGIN_MAX_FAILURES_PER_ACCOUNT, 0));
        self::assertTrue(login_throttled(0, LOGIN_MAX_FAILURES_PER_IP));
    }
}
