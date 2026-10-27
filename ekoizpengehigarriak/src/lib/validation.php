<?php

declare(strict_types=1);

// Checks for what users type in. The originals stored whatever arrived; these say which field is
// wrong, in the words the form shows.

const PASSWORD_MIN_LENGTH = 12;
const PASSWORD_MAX_LENGTH = 128;

const REQUIRED = 'Eremu hau bete behar da.';

/**
 * Why the new password can't be used, or null when it can. Length is what makes a password
 * hard to guess, so there are no rules about digits or symbols.
 */
function password_problem(string $password, string $confirm, string $email = ''): ?string
{
    $length = mb_strlen($password);
    if ($length < PASSWORD_MIN_LENGTH) {
        return 'Pasahitzak gutxienez ' . PASSWORD_MIN_LENGTH . ' karaktere izan behar ditu.';
    }
    if ($length > PASSWORD_MAX_LENGTH) {
        return 'Pasahitzak gehienez ' . PASSWORD_MAX_LENGTH . ' karaktere izan ditzake.';
    }
    if ($password !== $confirm) {
        return 'Pasahitzak ez datoz bat.';
    }
    if ($email !== '' && mb_strtolower($password) === mb_strtolower($email)) {
        return 'Pasahitza eta emaila ezin dira berdinak izan.';
    }

    return null;
}

/**
 * Text a person could type: valid UTF-8, without control characters such as line breaks.
 */
function is_plain_text(string $value): bool
{
    return mb_check_encoding($value, 'UTF-8') && preg_match('/[\x00-\x1F\x7F]/', $value) === 0;
}

function is_email(string $value): bool
{
    return strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * The sign-up form. The email is the username, kept in lower case so the same address can't be
 * registered twice with different capitals.
 *
 * @param array<mixed> $input the form's data[...] fields
 * @return array{0: array<string, string>, 1: array<string, string>} the values, and an error per wrong field
 */
function validate_registration(array $input): array
{
    $data = [];
    foreach (['email', 'firstname', 'lastname', 'city', 'stateProv', 'country', 'postcode', 'telephone'] as $field) {
        $value = $input[$field] ?? '';
        $data[$field] = is_string($value) ? trim($value) : '';
    }
    // Passwords as typed: a space at either end is part of them
    foreach (['password', 'password2'] as $field) {
        $value = $input[$field] ?? '';
        $data[$field] = is_string($value) ? $value : '';
    }
    $data['email'] = mb_strtolower($data['email']);
    $data['telephone'] = (string) preg_replace('/[\s.-]/', '', $data['telephone']);

    $errors = [];
    if (! is_email($data['email'])) {
        $errors['email'] = 'Email helbidea ez da zuzena.';
    }
    foreach (['firstname', 'lastname', 'city', 'stateProv', 'country'] as $field) {
        if ($data[$field] === '') {
            $errors[$field] = REQUIRED;
        } elseif (mb_strlen($data[$field]) > 60 || ! is_plain_text($data[$field])) {
            $errors[$field] = 'Gehienez 60 karaktere, lerro-jauzirik gabe.';
        }
    }
    if (preg_match('/^\d{5}$/', $data['postcode']) !== 1) {
        $errors['postcode'] = 'Posta-kodeak 5 zenbaki izan behar ditu.';
    }
    if (preg_match('/^\+?\d{9,15}$/', $data['telephone']) !== 1) {
        $errors['telephone'] = 'Telefonoak 9 eta 15 zenbaki artean izan behar ditu.';
    }
    $problem = password_problem($data['password'], $data['password2'], $data['email']);
    if ($problem !== null) {
        $errors['password'] = $problem;
    }

    return [$data, $errors];
}

/**
 * "12", "12.5" or "12,50" as "12.50"; null for anything that isn't a price from 0 to 99999.99.
 */
function parse_price(string $value): ?string
{
    $value = str_replace(',', '.', trim($value));
    if (preg_match('/^\d{1,5}(\.\d{1,2})?$/', $value) !== 1) {
        return null;
    }

    return number_format((float) $value, 2, '.', '');
}

/**
 * A new product, from the upload form.
 *
 * @param array<mixed> $input
 * @return array{0: array{izena: string, deskripzioa: string, salneurria: string, stock: int}, 1: array<string, string>}
 */
function validate_new_product(array $input): array
{
    $text = static fn (string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';

    $izena = $text('izena');
    $deskripzioa = $text('deskripzioa');
    $salneurria = parse_price($text('salneurria'));
    $stock = filter_var($text('stock'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99999]]);

    $errors = [];
    if ($izena === '' || mb_strlen($izena) > 30 || ! is_plain_text($izena)) {
        $errors['izena'] = 'Izenak 1 eta 30 karaktere artean izan behar ditu.';
    }
    if ($deskripzioa === '' || mb_strlen($deskripzioa) > 150 || ! is_plain_text($deskripzioa)) {
        $errors['deskripzioa'] = 'Deskripzioak 1 eta 150 karaktere artean izan behar ditu.';
    }
    if ($salneurria === null) {
        $errors['salneurria'] = 'Salneurria zenbaki bat da, adibidez 12,50.';
    }
    if ($stock === false) {
        $errors['stock'] = 'Stocka 0 eta 99999 arteko zenbaki osoa da.';
    }

    return [[
        'izena' => $izena,
        'deskripzioa' => $deskripzioa,
        'salneurria' => $salneurria ?? '0.00',
        'stock' => $stock === false ? 0 : $stock,
    ], $errors];
}

/**
 * A product's new description and, optionally, price. An empty price keeps the old one.
 *
 * @param array<mixed> $input
 * @return array{0: array{deskripzioa: string, salneurria: string|null}, 1: array<string, string>}
 */
function validate_product_update(array $input): array
{
    $deskripzioa = is_string($input['deskripzioa'] ?? null) ? trim($input['deskripzioa']) : '';
    $rawPrice = is_string($input['salneurria'] ?? null) ? trim($input['salneurria']) : '';
    $salneurria = $rawPrice === '' ? null : parse_price($rawPrice);

    $errors = [];
    // Line breaks are fine in a description
    if ($deskripzioa === '' || mb_strlen($deskripzioa) > 150 || ! mb_check_encoding($deskripzioa, 'UTF-8')) {
        $errors['deskripzioa'] = 'Deskripzioak 1 eta 150 karaktere artean izan behar ditu.';
    }
    if ($rawPrice !== '' && $salneurria === null) {
        $errors['salneurria'] = 'Salneurria zenbaki bat da, adibidez 12,50.';
    }

    return [['deskripzioa' => $deskripzioa, 'salneurria' => $salneurria], $errors];
}

/**
 * A search word for SQL LIKE: % and _ are found as themselves, not as wildcards. Goes with
 * ESCAPE '!' in the query.
 */
function like_pattern(string $word): string
{
    return '%' . strtr($word, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
}
