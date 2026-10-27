<?php

declare(strict_types=1);

/**
 * Sends the browser to another page of the shop and stops. 303, so a refresh after a form
 * doesn't send the form again.
 */
function redirect(string $to): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $to, true, 303);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? $value : '';
}

/**
 * A positive whole number from the query string, such as a product id; null for anything else.
 */
function query_id(string $key): ?int
{
    $id = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

function post_id(string $key): ?int
{
    $id = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? null : $id;
}

/**
 * A message for the next page, such as "Pasahitza aldatu da." after a form.
 */
function flash(string $text, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}

/**
 * @return array{type: string, text: string}|null
 */
function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if (! is_array($flash) || ! is_string($flash['type'] ?? null) || ! is_string($flash['text'] ?? null)) {
        return null;
    }

    return ['type' => $flash['type'], 'text' => $flash['text']];
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($ip) ? $ip : '';
}
