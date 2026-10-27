<?php

declare(strict_types=1);

/**
 * Every page index.php can show: the action, its file in includes/ and who may open it. Any
 * other action is "not found", so the action parameter can never choose a file to include.
 *
 *   public: anyone; user: logged in; admin: logged in with an admin account
 */
const ROUTES = [
    'home' => ['file' => 'main.php', 'access' => 'public'],
    'search' => ['file' => 'main.php', 'access' => 'public'],
    'about' => ['file' => 'about.php', 'access' => 'public'],
    'login' => ['file' => 'login.php', 'access' => 'public'],
    'register' => ['file' => 'register.php', 'access' => 'public'],
    'account' => ['file' => 'account.php', 'access' => 'user'],
    'photo' => ['file' => 'photo.php', 'access' => 'user'],
    'logout' => ['file' => 'logout.php', 'access' => 'user'],
    'description' => ['file' => 'description.php', 'access' => 'admin'],
    'updel' => ['file' => 'updel.php', 'access' => 'admin'],
];

/**
 * @return array{file: string, access: string}|null null when there is no such page
 */
function route_for(mixed $action): ?array
{
    if ($action === null || $action === '') {
        return ROUTES['home'];
    }
    if (! is_string($action) || ! array_key_exists($action, ROUTES)) {
        return null;
    }

    return ROUTES[$action];
}

/**
 * Whether a visitor may open a page: $user is null for a guest.
 *
 * @param array{access: string} $route
 * @param array{is_admin: bool}|null $user
 */
function may_open(array $route, ?array $user): bool
{
    return match ($route['access']) {
        'public' => true,
        'user' => $user !== null,
        'admin' => $user !== null && $user['is_admin'],
        default => false,
    };
}
