<?php

declare(strict_types=1);

// Gives an account a new random password and prints it once. The database ships without any
// password that works (db/init/01-bdweb.sql), so this is how the first admin logs in:
//
//   docker compose exec web php bin/set-password.php admin@bdweb
//
// Change it afterwards on the account page. Every session of that account is logged out.

require __DIR__ . '/../lib/app.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$username = mb_strtolower(trim($argv[1] ?? ''));
if ($username === '') {
    fwrite(STDERR, "Usage: php bin/set-password.php <username>\n");
    exit(2);
}

// 20 characters from an alphabet without look-alikes (0/O, 1/l/I)
$alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$password = '';
for ($i = 0; $i < 20; $i++) {
    $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}

$update = db()->prepare('UPDATE users SET password = ?, session_version = session_version + 1 WHERE username = ?');
$update->execute([hash_password($password), $username]);
if ($update->rowCount() !== 1) {
    fwrite(STDERR, "There is no user $username\n");
    exit(1);
}

echo "New password for $username: $password\n";
