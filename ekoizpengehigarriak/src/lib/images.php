<?php

declare(strict_types=1);

// Uploaded pictures: products (public, in public/images) and profile photos (private, in
// storage/perfiles). The originals saved any file under the name it came with, so a script with
// a .php name could be uploaded and run. Now only real pictures are kept, redrawn, under a
// random name.

final class UploadRejected extends RuntimeException
{
}

const IMAGE_MAX_BYTES = 2 * 1024 * 1024;
const IMAGE_MAX_SIDE = 4000;
const IMAGE_RULES = 'Irudiak JPEG, PNG, GIF edo WebP izan behar du, gehienez 2 MB eta 4000 × 4000 pixel.';

// What each kind of picture is saved as: a GIF becomes a PNG
const IMAGE_EXTENSIONS = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG => 'png',
    IMAGETYPE_GIF => 'png',
    IMAGETYPE_WEBP => 'webp',
];

/**
 * Saves one uploaded picture from $_FILES into $dir and returns its new file name.
 *
 * @throws UploadRejected with a message for the form
 */
function store_upload(mixed $upload, string $dir): string
{
    if (! is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new UploadRejected('Aukeratu irudi bat.');
    }
    $tmp = $upload['tmp_name'] ?? null;
    if ($upload['error'] !== UPLOAD_ERR_OK || ! is_string($tmp) || ! is_uploaded_file($tmp)) {
        throw new UploadRejected(IMAGE_RULES);
    }

    return store_image($tmp, $dir);
}

/**
 * Checks the picture at $path and redraws it into $dir under a random name, which it returns.
 * Redrawing keeps only the pixels: no metadata such as a GPS location, and nothing hidden after
 * the image data. The name has no part of the uploaded one, and its extension comes from the
 * picture's real type.
 *
 * @throws UploadRejected with a message for the form
 */
function store_image(string $path, string $dir): string
{
    $bytes = @filesize($path);
    if ($bytes === false || $bytes === 0 || $bytes > IMAGE_MAX_BYTES) {
        throw new UploadRejected(IMAGE_RULES);
    }
    $info = @getimagesize($path);
    if ($info === false || ! isset(IMAGE_EXTENSIONS[$info[2]])
        || $info[0] < 1 || $info[1] < 1 || $info[0] > IMAGE_MAX_SIDE || $info[1] > IMAGE_MAX_SIDE) {
        throw new UploadRejected(IMAGE_RULES);
    }

    $image = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_GIF => @imagecreatefromgif($path),
        default => @imagecreatefromwebp($path),
    };
    if ($image === false) {
        throw new UploadRejected(IMAGE_RULES);
    }

    $extension = IMAGE_EXTENSIONS[$info[2]];
    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    $target = rtrim($dir, '/') . '/' . $name;
    imagesavealpha($image, true);
    $saved = match ($extension) {
        'jpg' => imagejpeg($image, $target, 85),
        'png' => imagepng($image, $target, 6),
        default => imagewebp($image, $target, 85),
    };
    if (! $saved) {
        throw new RuntimeException("Can't write the picture to $dir");
    }

    return $name;
}

/**
 * A file name the shop wrote or shipped: letters, digits, dots, dashes and underscores, with no
 * directory in it.
 */
function is_safe_file_name(string $name): bool
{
    return preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]{0,99}$/', $name) === 1 && ! str_contains($name, '..');
}

/**
 * Deletes a picture from $dir by the name stored in the database, if that name is safe.
 */
function delete_image(string $name, string $dir): void
{
    $path = rtrim($dir, '/') . '/' . $name;
    if (is_safe_file_name($name) && is_file($path)) {
        unlink($path);
    }
}
