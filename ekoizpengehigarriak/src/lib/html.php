<?php

declare(strict_types=1);

/**
 * Text for HTML: every special character escaped, so data from a user or the database is shown
 * as text and can't become markup or a script (XSS).
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/**
 * A price the way the shop shows it: 12 becomes "12,00 €".
 */
function price(string|int|float $value): string
{
    return number_format((float) $value, 2, ',', '.') . ' €';
}
