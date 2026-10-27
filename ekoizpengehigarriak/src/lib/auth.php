<?php

declare(strict_types=1);

// Logging in, and the session that remembers it.

// More wrong passwords than this within LOGIN_FAILURE_MINUTES, for one account or from one IP,
// and logging in is refused until they age out: guessing passwords takes forever
const LOGIN_MAX_FAILURES_PER_ACCOUNT = 5;
const LOGIN_MAX_FAILURES_PER_IP = 20;
const LOGIN_FAILURE_MINUTES = 15;

/**
 * Starts the session with a hardened cookie, and ends it when it was idle too long. Returns true
 * when a logged-in session was just ended that way.
 */
function start_session(int $idleMinutes): bool
{
    // __Host-: the browser keeps the cookie for this host only and sends it only over HTTPS.
    // HttpOnly: scripts can't read it. SameSite=Strict: other sites can't make the browser send it.
    session_name('__Host-bdweb');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    $now = time();
    $lastSeen = $_SESSION['last_seen'] ?? null;
    $ended = isset($_SESSION['user_id']) && is_int($lastSeen) && $now - $lastSeen > $idleMinutes * 60;
    if ($ended) {
        log_out();
    }
    $_SESSION['last_seen'] = $now;

    return $ended;
}

function hash_password(string $password): string
{
    // Argon2id, salted and slow on purpose; the original stored unsalted MD5
    return password_hash($password, PASSWORD_ARGON2ID);
}

function login_throttled(int $accountFailures, int $ipFailures): bool
{
    return $accountFailures >= LOGIN_MAX_FAILURES_PER_ACCOUNT || $ipFailures >= LOGIN_MAX_FAILURES_PER_IP;
}

/**
 * Checks a login and, when it's right, logs the session in.
 *
 * @return 'ok'|'wrong'|'throttled'
 */
function attempt_login(string $username, string $password): string
{
    $username = mb_substr(mb_strtolower(trim($username)), 0, 254);
    $ip = client_ip();
    $db = db();

    $failures = $db->prepare('SELECT COUNT(*) FROM login_failures WHERE username = ? AND failed_at > NOW() - INTERVAL ? MINUTE');
    $failures->execute([$username, LOGIN_FAILURE_MINUTES]);
    $accountFailures = (int) $failures->fetchColumn();
    $failures = $db->prepare('SELECT COUNT(*) FROM login_failures WHERE ip = ? AND failed_at > NOW() - INTERVAL ? MINUTE');
    $failures->execute([$ip, LOGIN_FAILURE_MINUTES]);
    if (login_throttled($accountFailures, (int) $failures->fetchColumn())) {
        return 'throttled';
    }

    $find = $db->prepare('SELECT id, password, session_version FROM users WHERE username = ?');
    $find->execute([$username]);
    $user = $find->fetch();
    // Without such an account a real hash is still checked, so an unknown email takes as long
    // as a wrong password and the timing doesn't tell which emails have an account
    $hash = is_array($user) ? (string) $user['password'] : hash_password(random_bytes(16));

    if (! password_verify($password, $hash) || ! is_array($user)) {
        $db->prepare('INSERT INTO login_failures (username, ip) VALUES (?, ?)')->execute([$username, $ip]);

        return 'wrong';
    }

    if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
        $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([hash_password($password), $user['id']]);
    }
    $db->prepare('DELETE FROM login_failures WHERE username = ?')->execute([$username]);
    log_in((int) $user['id'], (int) $user['session_version']);

    return 'ok';
}

function log_in(int $userId, int $sessionVersion): void
{
    // A new session id: one somebody planted before the login is worthless (session fixation)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['session_version'] = $sessionVersion;
    unset($_SESSION['csrf']);
    current_user(refresh: true);
}

function log_out(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    current_user(refresh: true);
}

/**
 * The logged-in user, read from the database on each request, so a deleted account or a
 * removed admin right takes effect at once. The session holds only the user's id, never the
 * password or its hash. A session from before the latest password change is logged out.
 *
 * @return array{id: int, username: string, izena: string, is_admin: bool, irudia: string}|null
 */
function current_user(bool $refresh = false): ?array
{
    static $rows = [];
    if ($refresh) {
        $rows = [];
    }

    $id = $_SESSION['user_id'] ?? null;
    if (! is_int($id)) {
        return null;
    }
    if (! array_key_exists($id, $rows)) {
        $find = db()->prepare('SELECT id, username, izena, is_admin, irudia, session_version FROM users WHERE id = ?');
        $find->execute([$id]);
        $row = $find->fetch();
        $rows[$id] = is_array($row) ? $row : null;
    }

    $row = $rows[$id];
    if ($row === null || (int) $row['session_version'] !== ($_SESSION['session_version'] ?? null)) {
        log_out();

        return null;
    }

    return [
        'id' => (int) $row['id'],
        'username' => (string) $row['username'],
        'izena' => (string) $row['izena'],
        'is_admin' => (bool) $row['is_admin'],
        'irudia' => (string) $row['irudia'],
    ];
}

function is_admin(): bool
{
    return current_user()['is_admin'] ?? false;
}
