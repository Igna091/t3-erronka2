<?php

declare(strict_types=1);

// The app's settings. The database login comes from the environment and a Docker secret
// (compose.yaml), never from the code. Inside Docker the database host is the service name,
// mysql, not localhost.

return [
    'enpresa' => 'Proiektua',
    'version' => '2.0',

    'db' => [
        'host' => getenv('DB_HOST') ?: 'mysql',
        'name' => getenv('DB_NAME') ?: 'bdweb',
        'user' => getenv('DB_USER') ?: 'bdweb_app',
        'password_file' => getenv('DB_PASSWORD_FILE') ?: '/run/secrets/db_app_password',
    ],

    // A session with no request for this long is logged out
    'session_idle_minutes' => 30,

    // Product pictures are public; profile photos are kept outside the web root
    'images_dir' => __DIR__ . '/public/images',
    'profiles_dir' => __DIR__ . '/storage/perfiles',
];
