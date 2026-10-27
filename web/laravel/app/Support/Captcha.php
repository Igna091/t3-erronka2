<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The picture check on sign-up, and on login after a few failed attempts. Drawn here with gd,
 * because the site has no route to the internet for a third-party service. The answer is kept in
 * the session as a hash and works once.
 */
final class Captcha
{
    private const SESSION_KEY = 'captcha';

    // No look-alikes (0/o, 1/l/i)
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const LENGTH = 5;

    private const MINUTES_VALID = 10;

    // Login asks for it after this many failures from one address or on one email...
    public const FAILURES_BEFORE_CAPTCHA = 3;

    // ...within this many seconds
    private const FAILURE_WINDOW = 900;

    /**
     * A new picture as PNG bytes; its answer replaces the previous one.
     */
    public static function image(Session $session): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        $session->put(self::SESSION_KEY, [
            'hash' => hash('sha256', $code),
            'expires' => time() + self::MINUTES_VALID * 60,
        ]);

        return self::draw($code);
    }

    /**
     * Whether the answer matches the last picture. Either way that picture is used up.
     */
    public static function passes(Session $session, mixed $answer): bool
    {
        $expected = $session->pull(self::SESSION_KEY);
        if (! is_array($expected) || ! is_string($answer) || $expected['expires'] < time()) {
            return false;
        }

        return hash_equals($expected['hash'], hash('sha256', Str::lower(preg_replace('/\s+/', '', $answer) ?? '')));
    }

    public static function requiredForLogin(Request $request, ?string $email): bool
    {
        foreach (self::failureKeys($request, $email) as $key) {
            if (RateLimiter::attempts($key) >= self::FAILURES_BEFORE_CAPTCHA) {
                return true;
            }
        }

        return false;
    }

    public static function recordFailedLogin(Request $request, ?string $email): void
    {
        foreach (self::failureKeys($request, $email) as $key) {
            RateLimiter::hit($key, self::FAILURE_WINDOW);
        }
    }

    /**
     * After a successful login only the email's count goes; the address's keeps counting down,
     * so logging in to one account doesn't reset an attack on others from the same place.
     */
    public static function clearFailedLogins(string $email): void
    {
        RateLimiter::clear('login-failures:email:'.self::normalise($email));
    }

    /**
     * @return list<string>
     */
    private static function failureKeys(Request $request, ?string $email): array
    {
        $keys = ['login-failures:ip:'.$request->ip()];
        if ($email !== null && $email !== '') {
            $keys[] = 'login-failures:email:'.self::normalise($email);
        }

        return $keys;
    }

    private static function normalise(string $email): string
    {
        return Str::transliterate(Str::lower(trim($email)));
    }

    /**
     * Draws each character with gd's built-in font, enlarged, turned and shifted a little, over
     * lines and dots, so the picture needs no font file.
     */
    private static function draw(string $code): string
    {
        $width = 200;
        $height = 64;
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));

        imagesetthickness($image, 2);
        for ($i = 0; $i < 5; $i++) {
            $grey = random_int(150, 200);
            imageline($image, random_int(0, 40), random_int(0, $height), random_int(160, $width), random_int(0, $height), (int) imagecolorallocate($image, $grey, $grey + 20, $grey + 10));
        }

        $inks = [[14, 58, 47], [42, 75, 215], [61, 95, 85]];
        foreach (str_split($code) as $i => $character) {
            $glyph = imagecreatetruecolor(10, 16);
            imagealphablending($glyph, false);
            imagesavealpha($glyph, true);
            $clear = (int) imagecolorallocatealpha($glyph, 255, 255, 255, 127);
            imagefill($glyph, 0, 0, $clear);
            [$r, $g, $b] = $inks[array_rand($inks)];
            imagechar($glyph, 5, 0, 0, $character, (int) imagecolorallocate($glyph, $r, $g, $b));

            $large = imagescale($glyph, 30, 48, IMG_BILINEAR_FIXED);
            if ($large === false) {
                continue;
            }
            imagealphablending($large, false);
            imagesavealpha($large, true);
            $turned = imagerotate($large, random_int(-18, 18), (int) imagecolorallocatealpha($large, 255, 255, 255, 127));
            if ($turned === false) {
                continue;
            }
            imagealphablending($image, true);
            imagecopy($image, $turned, 14 + $i * 36 + random_int(-3, 3), random_int(2, 10), 0, 0, imagesx($turned), imagesy($turned));
        }

        for ($i = 0; $i < 120; $i++) {
            $grey = random_int(120, 210);
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), (int) imagecolorallocate($image, $grey, $grey, $grey));
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
