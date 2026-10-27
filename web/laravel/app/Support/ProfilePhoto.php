<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Profile photos. An upload is decoded and drawn again as a 512 px WebP, which drops anything
 * hidden in the file (scripts, a second format, the camera's location), and kept on the private
 * disk, outside public/: only its owner gets it, through ProfileController::photo().
 */
final class ProfilePhoto
{
    private const SIZE = 512;

    // Decoding takes about 4 bytes a pixel, against PHP's 128 MB
    private const MAX_PIXELS = 4096 * 4096;

    public static function store(User $user, UploadedFile $file): void
    {
        $bytes = (string) $file->get();
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['photo' => 'We can’t read that picture. Use a JPEG, PNG or WebP photo.']);
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['photo' => 'That picture is too big. Use one of 16 megapixels or less.']);
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw ValidationException::withMessages(['photo' => 'We can’t read that picture. Use a JPEG, PNG or WebP photo.']);
        }

        // The middle square, so a face in a portrait photo stays in
        $side = min($info[0], $info[1]);
        $photo = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($photo, $source, 0, 0, intdiv($info[0] - $side, 2), intdiv($info[1] - $side, 2), self::SIZE, self::SIZE, $side, $side);
        ob_start();
        imagewebp($photo, null, 82);
        $webp = (string) ob_get_clean();

        $path = 'profile-photos/'.Str::uuid().'.webp';
        Storage::disk('local')->put($path, $webp);
        self::deleteFile($user->profile_image);
        $user->forceFill(['profile_image' => $path])->save();
    }

    public static function delete(User $user): void
    {
        self::deleteFile($user->profile_image);
        $user->forceFill(['profile_image' => null])->save();
    }

    private static function deleteFile(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('local')->delete($path);
        }
    }
}
