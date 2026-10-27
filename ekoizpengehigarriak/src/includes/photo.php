<?php

declare(strict_types=1);

// The logged-in user's own profile photo. Photos are kept outside the web root
// (storage/perfiles), so the only way to see one is this page, and it shows only your own.

$user = current_user();
$name = $user['irudia'] ?? '';
$path = config()['profiles_dir'] . '/' . $name;

if ($name === '' || ! is_safe_file_name($name) || ! is_file($path)) {
    http_response_code(404);
    exit;
}

$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$type = $types[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
readfile($path);
