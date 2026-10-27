<?php

namespace App\Support;

/**
 * Names a browser the way the Security page and the alert emails show it: "Firefox on Windows".
 */
final class Device
{
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'Firefox/' => 'Firefox',
        'FxiOS/' => 'Firefox',
        'CriOS/' => 'Chrome',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    private const SYSTEMS = [
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'Mac OS X' => 'macOS',
        'CrOS' => 'ChromeOS',
        'Linux' => 'Linux',
    ];

    public static function describe(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'An unknown browser';
        }

        return self::first(self::BROWSERS, $userAgent, 'A browser').' on '.self::first(self::SYSTEMS, $userAgent, 'an unknown system');
    }

    /**
     * @param  array<string, string>  $names
     */
    private static function first(array $names, string $userAgent, string $default): string
    {
        foreach ($names as $needle => $name) {
            if (str_contains($userAgent, $needle)) {
                return $name;
            }
        }

        return $default;
    }
}
