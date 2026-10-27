<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Uploaded pictures: only real pictures are kept, redrawn, under a random name with the
 * extension of their real type.
 */
final class ImagesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/images-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    /**
     * @param 'jpeg'|'png'|'gif'|'webp' $type
     * @param positive-int $width
     * @param positive-int $height
     */
    private function picture(string $type, int $width = 40, int $height = 30): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 128, 0, 0));
        $path = $this->dir . '/upload-' . bin2hex(random_bytes(4));
        match ($type) {
            'jpeg' => imagejpeg($image, $path),
            'png' => imagepng($image, $path),
            'gif' => imagegif($image, $path),
            'webp' => imagewebp($image, $path),
        };

        return $path;
    }

    private function file(string $contents): string
    {
        $path = $this->dir . '/upload-' . bin2hex(random_bytes(4));
        file_put_contents($path, $contents);

        return $path;
    }

    public function testAPictureIsRedrawnUnderARandomName(): void
    {
        $name = store_image($this->picture('jpeg'), $this->dir);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', $name);
        $info = getimagesize($this->dir . '/' . $name);
        self::assertNotFalse($info);
        self::assertSame([40, 30, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);
    }

    public function testTheExtensionComesFromTheRealType(): void
    {
        self::assertStringEndsWith('.png', store_image($this->picture('png'), $this->dir));
        self::assertStringEndsWith('.webp', store_image($this->picture('webp'), $this->dir));
        // A GIF is kept as a PNG
        self::assertStringEndsWith('.png', store_image($this->picture('gif'), $this->dir));
    }

    public function testTwoUploadsNeverShareAName(): void
    {
        $path = $this->picture('png');

        self::assertNotSame(store_image($path, $this->dir), store_image($path, $this->dir));
    }

    public function testAScriptIsNotAPicture(): void
    {
        $this->expectException(UploadRejected::class);

        store_image($this->file('<?php echo "hello"; ?>'), $this->dir);
    }

    public function testAPictureHeaderWithNothingBehindItIsRefused(): void
    {
        // The first bytes of a GIF, then text: enough to fool a check of the first bytes only
        $this->expectException(UploadRejected::class);

        store_image($this->file("GIF89a\x01\x00\x01\x00<?php echo 1; ?>"), $this->dir);
    }

    public function testAnEmptyFileIsRefused(): void
    {
        $this->expectException(UploadRejected::class);

        store_image($this->file(''), $this->dir);
    }

    public function testAPictureTooBigIsRefused(): void
    {
        $this->expectException(UploadRejected::class);

        store_image($this->picture('png', IMAGE_MAX_SIDE + 1, 1), $this->dir);
    }

    public function testNoUploadIsAMessageForTheForm(): void
    {
        $this->expectExceptionMessage('Aukeratu irudi bat.');

        store_upload(['error' => UPLOAD_ERR_NO_FILE], $this->dir);
    }

    public function testAFileThatWasntUploadedIsRefused(): void
    {
        // is_uploaded_file(): a form can't name a file already on the server
        $this->expectException(UploadRejected::class);

        store_upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => $this->picture('png')], $this->dir);
    }

    public function testSafeFileNames(): void
    {
        self::assertTrue(is_safe_file_name('generikoa.jpg'));
        self::assertTrue(is_safe_file_name('0f3a9c.webp'));
        self::assertFalse(is_safe_file_name(''));
        self::assertFalse(is_safe_file_name('../conf.php'));
        self::assertFalse(is_safe_file_name('a/b.jpg'));
        self::assertFalse(is_safe_file_name('.htaccess'));
        self::assertFalse(is_safe_file_name('a..jpg'));
    }

    public function testDeletingOnlyTouchesTheFolder(): void
    {
        $outside = $this->file('keep me');
        $name = store_image($this->picture('png'), $this->dir);

        delete_image('../' . basename($this->dir) . '/' . basename($outside), $this->dir);
        delete_image($name, $this->dir);

        self::assertFileExists($outside);
        self::assertFileDoesNotExist($this->dir . '/' . $name);
    }
}
