<?php

declare(strict_types=1);

/**
 * The settings in conf.php.
 *
 * @return array{enpresa: string, version: string, db: array{host: string, name: string, user: string, password_file: string}, session_idle_minutes: int, images_dir: string, profiles_dir: string}
 */
function config(): array
{
    static $config = null;

    return $config ??= require __DIR__ . '/../conf.php';
}

/**
 * The connection to MySQL, opened on first use. It logs in as the app's own user, which may
 * only read and write rows in bdweb (db/init/02-app-user.sh), never as root. Errors throw
 * exceptions, which index.php logs without showing them, and every query is a prepared
 * statement: values travel apart from the SQL, so they can't change it (SQL injection).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $settings = config()['db'];
        $password = @file_get_contents($settings['password_file']);
        if ($password === false) {
            throw new RuntimeException("Can't read the database password from {$settings['password_file']}");
        }
        $pdo = new PDO(
            "mysql:host={$settings['host']};dbname={$settings['name']};charset=utf8mb4",
            $settings['user'],
            trim($password),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements on the server, not ones PDO imitates by quoting
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    return $pdo;
}

/**
 * Every row a prepared statement returns.
 *
 * @param list<scalar|null> $params
 * @return array<int, array<string, mixed>>
 */
function rows(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}
