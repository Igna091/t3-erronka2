<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Refuses a password that appears in a list of leaked ones, which attackers try first. The list
 * (resources/passwords/) is in the image, because the site can't reach an online service.
 */
final class NotLeakedPassword implements ValidationRule
{
    /** @var array<string, int>|null */
    private static ?array $leaked = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && isset(self::leaked()[Str::lower($value)])) {
            $fail('That password appears in lists of leaked passwords, so attackers try it first. Choose another one.');
        }
    }

    /**
     * @return array<string, int>
     */
    private static function leaked(): array
    {
        return self::$leaked ??= array_flip(file(resource_path('passwords/leaked.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
    }
}
