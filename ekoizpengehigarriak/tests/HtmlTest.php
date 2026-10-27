<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Output escaping: what a user typed is shown as text, never run as markup (XSS).
 */
final class HtmlTest extends TestCase
{
    public function testMarkupBecomesText(): void
    {
        self::assertSame('&lt;b&gt;produktu&lt;/b&gt;', e('<b>produktu</b>'));
    }

    public function testQuotesCantCloseAnAttribute(): void
    {
        self::assertSame('a&quot; onmouseover=&quot;x', e('a" onmouseover="x'));
        self::assertSame('it&apos;s', e("it's"));
    }

    public function testInvalidUtf8IsReplacedNotDropped(): void
    {
        self::assertSame("a\u{FFFD}b", e("a\xC3b"));
    }

    public function testNumbersAndNull(): void
    {
        self::assertSame('12', e(12));
        self::assertSame('', e(null));
    }

    public function testPrices(): void
    {
        self::assertSame('12,00 €', price('12'));
        self::assertSame('1.234,50 €', price(1234.5));
    }
}
