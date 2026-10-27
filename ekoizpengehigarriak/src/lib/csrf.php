<?php

declare(strict_types=1);

/**
 * Protection against cross-site request forgery: every form that changes something carries a
 * random token from the session, and the change is refused unless the token comes back. Another
 * site can make a browser send a request, but it can't read the token.
 */
function csrf_token(): string
{
    if (! isset($_SESSION['csrf']) || ! is_string($_SESSION['csrf']) || $_SESSION['csrf'] === '') {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(mixed $sent): bool
{
    $expected = $_SESSION['csrf'] ?? null;

    return is_string($sent) && is_string($expected) && $expected !== '' && hash_equals($expected, $sent);
}
