<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Enicore\Rivena\Classes\Disk;
use InvalidArgumentException;
use RuntimeException;
use Slim\Psr7\UploadedFile;
use Tests\Support\TestConfig;

#[CoversClass(\Enicore\Rivena\Classes\Disk::class)]
class DiskTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/DiskTest_' . uniqid();
        mkdir($this->tempDir);
        TestConfig::load();
    }

    protected function tearDown(): void
    {
        $this->cleanUpDir($this->tempDir);
    }

    private function cleanUpDir(string $dir): void
    {
        if (is_dir($dir)) {
            foreach (scandir($dir) as $item) {
                if ($item !== '.' && $item !== '..') {
                    $path = $dir . '/' . $item;
                    is_dir($path) ? $this->cleanUpDir($path) : unlink($path);
                }
            }
            rmdir($dir);
        }
    }

    public function testGetStructuredDirectory(): void
    {
        $id = 12345;
        $path = Disk::getStructuredDirectory($id, 500, 2);
        $this->assertSame('000/024/', $path);

        $path = Disk::getStructuredDirectory($id, 100, 3);
        $this->assertSame('000/000/123/', $path);
    }

    public function testEnsureFileName(): void
    {
        $input = 'invalid:/\file*name.txt';
        $output = Disk::ensureFileName($input);
        $this->assertSame('invalid_file_name.txt', $output);

        $input = '';
        $output = Disk::ensureFileName($input);
        $this->assertSame('unknown', $output);

        // no leading or trailing dots: no "..", nor hidden files like .htaccess
        $this->assertSame('unknown', Disk::ensureFileName('..'));
        $this->assertSame('htaccess', Disk::ensureFileName('.htaccess'));
        $this->assertSame('file', Disk::ensureFileName('../file.'));

        // control characters (a NUL would make the file functions throw)
        $this->assertSame('a_b_c', Disk::ensureFileName("a\0b\x1Fc\n"));

        // at most 255 bytes, with the extension, not cutting a character
        $this->assertSame(str_repeat('a', 251) . '.pdf', Disk::ensureFileName(str_repeat('a', 300) . '.pdf'));
        $name = Disk::ensureFileName(str_repeat('ä', 200) . '.txt');
        $this->assertSame(str_repeat('ä', 125) . '.txt', $name);
        $this->assertSame(str_repeat('b', 255), Disk::ensureFileName(str_repeat('b', 300) . '.' . str_repeat('c', 30)));
    }

    public function testGetUniqueFileName(): void
    {
        $uniqueFileName = Disk::getUniqueFileName($this->tempDir, 'txt', 'test');
        $this->assertStringEndsWith('.txt', $uniqueFileName);
        $this->assertStringStartsWith('test', $uniqueFileName);
        // random, not from the time
        $this->assertMatchesRegularExpression('/^test[0-9a-f]{32}\.txt$/', $uniqueFileName);

        // Cleanup file if created
        if (file_exists($this->tempDir . '/' . $uniqueFileName)) {
            unlink($this->tempDir . '/' . $uniqueFileName);
        }
    }

    public function testMakeDir(): void
    {
        $newDir = $this->tempDir . '/new_directory';
        $result = Disk::makeDir($newDir);
        $this->assertTrue($result);
        $this->assertDirectoryExists($newDir);

        // Cleanup
        rmdir($newDir);
    }

    public function testRemoveDir(): void
    {
        $dir = $this->tempDir . '/directory_to_remove';
        mkdir($dir);
        touch($dir . '/file.txt');

        $result = Disk::removeDir($dir);
        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function testDeleteOutdatedFiles(): void
    {
        $file = $this->tempDir . '/old_file.txt';
        touch($file, time() - 3600); // File is 1 hour old

        $result = Disk::deleteOutdatedFiles($this->tempDir, 1800); // Delete files older than 30 minutes
        $this->assertTrue($result);
        $this->assertFileDoesNotExist($file);
    }

    public function testGetMaxFileUploadSize(): void
    {
        $size = Disk::getMaxFileUploadSize();
        $this->assertIsInt($size);
        $this->assertGreaterThan(0, $size);
    }

    public function testGetFileMimeType(): void
    {
        $file = $this->tempDir . '/test.txt';
        file_put_contents($file, 'This is a test file.');
        $mimeType = Disk::getFileMimeType($file);
        $this->assertSame('text/plain', $mimeType);

        // Cleanup
        unlink($file);
    }

    public function testGetExtension(): void
    {
        $this->assertSame('jpg', Disk::getExtension('/path/photo.JPG'));
        $this->assertSame('gz', Disk::getExtension('archive.tar.gz'));
        $this->assertSame('', Disk::getExtension('README'));
    }

    public function testSizeToBytes(): void
    {
        $this->assertSame(67108864, Disk::sizeToBytes('64M'));
        $this->assertSame(1536, Disk::sizeToBytes('1.5k'));
        $this->assertSame(2147483648, Disk::sizeToBytes('2G'));
        $this->assertSame(1000, Disk::sizeToBytes('1000'));
        $this->assertSame(0, Disk::sizeToBytes('0'));
    }

    public function testGetMaxFileUploadSizeLimits(): void
    {
        // the settings can't be changed at runtime: read them in a new process
        $get = function (string $post, string $upload): int {
            $autoload = escapeshellarg(__DIR__ . '/../../vendor/autoload.php');
            $code = escapeshellarg("require $autoload; echo Enicore\\Rivena\\Classes\\Disk::getMaxFileUploadSize();");
            return (int)shell_exec(PHP_BINARY . " -d post_max_size=$post -d upload_max_filesize=$upload -r $code");
        };

        $this->assertSame(2097152, $get('8M', '2M'));
        $this->assertSame(1048576, $get('1M', '2M'));
        // 0 is no limit
        $this->assertSame(2097152, $get('0', '2M'));
        $this->assertSame(8388608, $get('8M', '0'));
        $this->assertSame(0, $get('0', '0'));
    }

    public function testMakeDirNested(): void
    {
        $dir = $this->tempDir . '/a/b/c';
        $this->assertTrue(Disk::makeDir($dir));
        $this->assertDirectoryExists($dir);
        // not writable for all
        $this->assertSame(0, fileperms("$this->tempDir/a") & 0002);
        // an existing directory is fine
        $this->assertTrue(Disk::makeDir($dir));
    }

    public function testRemoveDirNested(): void
    {
        $dir = $this->tempDir . '/remove';
        mkdir($dir . '/sub/subsub', 0777, true);
        touch($dir . '/file.txt');
        touch($dir . '/sub/subsub/file.txt');

        $this->assertTrue(Disk::removeDir($dir, contentsOnly: true));
        $this->assertDirectoryExists($dir);
        $this->assertSame(['.', '..'], scandir($dir));

        $this->assertTrue(Disk::removeDir($dir . '/'));
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertTrue(Disk::removeDir($dir));
        $this->assertTrue(Disk::removeDir(''));
    }

    public function testRemoveDirLinks(): void
    {
        $dir = $this->tempDir . '/remove';
        $targetDir = $this->tempDir . '/target';
        mkdir($dir);
        mkdir($targetDir);
        touch($targetDir . '/file.txt');
        touch($this->tempDir . '/file.txt');
        symlink($targetDir, $dir . '/dirLink');
        symlink($this->tempDir . '/file.txt', $dir . '/fileLink');

        // the links are removed, not their targets
        $this->assertTrue(Disk::removeDir($dir));
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertFileExists($targetDir . '/file.txt');
        $this->assertFileExists($this->tempDir . '/file.txt');

        // following the links removes the targets too
        mkdir($dir);
        symlink($targetDir, $dir . '/dirLink');
        symlink($this->tempDir . '/file.txt', $dir . '/fileLink');
        $this->assertTrue(Disk::removeDir($dir, true));
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertDirectoryDoesNotExist($targetDir);
        $this->assertFileDoesNotExist($this->tempDir . '/file.txt');
    }

    public function testRemoveDirGivenLink(): void
    {
        $link = $this->tempDir . '/link';
        $targetDir = $this->tempDir . '/target';
        mkdir($targetDir);
        touch($targetDir . '/file.txt');
        symlink($targetDir, $link);

        // only the link goes, with or without the slash; emptying it does nothing
        $this->assertFalse(Disk::removeDir($link . '/', contentsOnly: true));
        $this->assertTrue(is_link($link));
        $this->assertTrue(Disk::removeDir($link . '/'));
        $this->assertFalse(is_link($link));
        $this->assertFileExists($targetDir . '/file.txt');

        // following it empties the target, or removes the target and the link
        symlink($targetDir, $link);
        $this->assertTrue(Disk::removeDir($link, true, true));
        $this->assertTrue(is_link($link));
        $this->assertSame(['.', '..'], scandir($targetDir));
        $this->assertTrue(Disk::removeDir($link, true));
        $this->assertFalse(is_link($link));
        $this->assertDirectoryDoesNotExist($targetDir);
    }

    public function testDeleteOutdatedFilesKeepsNewFiles(): void
    {
        touch($this->tempDir . '/old.txt', time() - 3600);
        touch($this->tempDir . '/new.txt');
        // directories stay
        mkdir($this->tempDir . '/dir');
        touch($this->tempDir . '/dir', time() - 3600);

        $this->assertTrue(Disk::deleteOutdatedFiles($this->tempDir . '/', '1800'));
        $this->assertFileDoesNotExist($this->tempDir . '/old.txt');
        $this->assertFileExists($this->tempDir . '/new.txt');
        $this->assertDirectoryExists($this->tempDir . '/dir');

        $this->assertFalse(Disk::deleteOutdatedFiles($this->tempDir . '/missing', 1800));
    }

    public function testGetFileMimeTypeImages(): void
    {
        file_put_contents($this->tempDir . '/image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->assertSame('image/svg', Disk::getFileMimeType($this->tempDir . '/image.svg'));
        $this->assertSame('image/x-icon', Disk::getFileMimeType(__DIR__ . '/../Fixtures/images/icon.ico'));
    }

    /**
     * Saves an uploaded file with saveUploadedImage() and gives back the result.
     */
    private function save(string $content, string $targetFile, int $maxSize = 0, int $maxWidth = 0,
                          int $maxHeight = 0, int $error = UPLOAD_ERR_OK, string $type = ''): string
    {
        $sourceFile = $this->tempDir . '/upload_' . uniqid();
        file_put_contents($sourceFile, $content);
        $file = new UploadedFile($sourceFile, 'name', null, strlen($content), $error);

        return Disk::saveUploadedImage($file, $targetFile, $maxSize, $maxWidth, $maxHeight, $type);
    }

    private function image(int $width, int $height, string $type): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        match ($type) {
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'jpg' => imagejpeg($image),
        };
        return ob_get_clean();
    }

    public function testSaveUploadedImageTypes(): void
    {
        // the target extension follows the image type, whatever the target name has
        $result = $this->save($this->image(10, 10, 'jpg'), $this->tempDir . '/out/a.png');
        $this->assertSame($this->tempDir . '/out/a.jpg', $result);
        $this->assertSame('image/jpeg', Disk::getFileMimeType($result));

        $result = $this->save($this->image(10, 10, 'png'), $this->tempDir . '/a.jpg');
        $this->assertSame($this->tempDir . '/a.png', $result);
        $this->assertSame('image/png', Disk::getFileMimeType($result));

        $result = $this->save($this->image(10, 10, 'gif'), $this->tempDir . '/b');
        $this->assertSame($this->tempDir . '/b.gif', $result);
        $this->assertSame('image/gif', Disk::getFileMimeType($result));
    }

    public function testSaveUploadedImageType(): void
    {
        $result = $this->save($this->image(10, 10, 'png'), $this->tempDir . '/a', type: 'jpg');
        $this->assertSame($this->tempDir . '/a.jpg', $result);
        $this->assertSame('image/jpeg', Disk::getFileMimeType($result));

        $result = $this->save($this->image(10, 10, 'jpg'), $this->tempDir . '/b.jpg', type: 'png');
        $this->assertSame($this->tempDir . '/b.png', $result);
        $this->assertSame('image/png', Disk::getFileMimeType($result));

        // transparency becomes white in jpgs
        $image = imagecreatetruecolor(10, 10);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $result = imagecreatefromjpeg($this->save(ob_get_clean(), $this->tempDir . '/c', type: 'jpg'));
        $this->assertGreaterThan(250, imagecolorsforindex($result, imagecolorat($result, 5, 5))['red']);

        // svgs stay svgs
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>';
        $this->assertSame($this->tempDir . '/e.svg', $this->save($svg, $this->tempDir . '/e', type: 'png'));
    }

    public function testSaveUploadedImageResize(): void
    {
        // the aspect ratio is kept
        $result = $this->save($this->image(200, 100, 'png'), $this->tempDir . '/a', 0, 50, 50);
        $this->assertSame([50, 25], array_slice(getimagesize($result), 0, 2));

        $result = $this->save($this->image(100, 200, 'jpg'), $this->tempDir . '/b', 0, 50, 50);
        $this->assertSame([25, 50], array_slice(getimagesize($result), 0, 2));

        // only the width
        $result = $this->save($this->image(100, 200, 'jpg'), $this->tempDir . '/c', 0, 50);
        $this->assertSame([50, 100], array_slice(getimagesize($result), 0, 2));

        // smaller images aren't enlarged
        $result = $this->save($this->image(20, 10, 'gif'), $this->tempDir . '/d', 0, 50, 50);
        $this->assertSame([20, 10], array_slice(getimagesize($result), 0, 2));
    }

    /**
     * A 40x20 jpg with a red, green, blue and yellow quarter (top left, top right, bottom left, bottom right), with
     * the EXIF data given (inserted after the jpg's first 2 bytes).
     */
    private function quartersJpeg(string $exif): string
    {
        $image = imagecreatetruecolor(40, 20);
        imagefilledrectangle($image, 0, 0, 19, 9, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 20, 0, 39, 9, imagecolorallocate($image, 0, 255, 0));
        imagefilledrectangle($image, 0, 10, 19, 19, imagecolorallocate($image, 0, 0, 255));
        imagefilledrectangle($image, 20, 10, 39, 19, imagecolorallocate($image, 255, 255, 0));
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = ob_get_clean();

        return substr($jpeg, 0, 2) . $exif . substr($jpeg, 2);
    }

    /**
     * The EXIF segment with the orientation, in the byte order ("II" little-endian, "MM" big-endian).
     */
    private function exif(int $orientation, string $order = 'II'): string
    {
        [$short, $long] = $order === 'MM' ? ['n', 'N'] : ['v', 'V'];
        $tiff = $order . pack($short, 42) . pack($long, 8) . pack($short, 2)
            . pack($short, 0x010F) . pack($short, 2) . pack($long, 4) . 'abc' . "\0"
            . pack($short, 0x0112) . pack($short, 3) . pack($long, 1) . pack($short, $orientation) . "\0\0"
            . pack($long, 0);

        return "\xFF\xE1" . pack('n', strlen($tiff) + 8) . "Exif\0\0" . $tiff;
    }

    /**
     * The saved image's size and its corners' colors (top left, top right, bottom left, bottom right) as letters.
     */
    private function corners(string $file): array
    {
        $image = imagecreatefromjpeg($file);
        [$width, $height] = [imagesx($image), imagesy($image)];
        $colors = [];

        foreach ([[2, 2], [$width - 3, 2], [2, $height - 3], [$width - 3, $height - 3]] as [$x, $y]) {
            $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            $colors[] = match ([$rgb['red'] > 128, $rgb['green'] > 128, $rgb['blue'] > 128]) {
                [true, false, false] => 'R',
                [false, true, false] => 'G',
                [false, false, true] => 'B',
                [true, true, false] => 'Y',
                default => '?',
            };
        }

        return [$width, $height, implode($colors)];
    }

    public static function orientations(): array
    {
        return [
            [1, 40, 20, 'RGBY'],
            [2, 40, 20, 'GRYB'],
            [3, 40, 20, 'YBGR'],
            [4, 40, 20, 'BYRG'],
            [5, 20, 40, 'RBGY'],
            [6, 20, 40, 'BRYG'],
            [7, 20, 40, 'YGBR'],
            [8, 20, 40, 'GYRB'],
        ];
    }

    #[DataProvider('orientations')]
    public function testSaveUploadedImageOrientation(int $orientation, int $width, int $height, string $corners): void
    {
        foreach (['II', 'MM'] as $order) {
            $result = $this->save($this->quartersJpeg($this->exif($orientation, $order)), $this->tempDir . "/$order");
            $this->assertSame([$width, $height, $corners], $this->corners($result), $order);
        }

        // turned before the resize, so the max width and height apply to the turned image
        $result = $this->save($this->quartersJpeg($this->exif($orientation)), $this->tempDir . '/small', 0, 10, 10);
        $this->assertSame([$width / 4, $height / 4], array_slice(getimagesize($result), 0, 2));
    }

    public function testSaveUploadedImageBrokenOrientation(): void
    {
        $exif = $this->exif(6);
        $broken = [
            'no EXIF' => '',
            'not EXIF' => "\xFF\xE1\x00\x0AExiv\0\0\0\0",
            'too short' => substr($exif, 0, 4) . "Exif\0\0II",
            'cut off' => "\xFF\xE1" . substr($exif, 2, 30),
            'list past the end' => "\xFF\xE1\x00\x10Exif\0\0II\x2A\x00\xFF\xFF\xFF\x7F",
            'entries past the end' => "\xFF\xE1\x00\x18Exif\0\0II\x2A\x00\x08\x00\x00\x00\x05\x00"
                . "\x12\x01\x03\x00\x01\x00",
            'orientation 9' => $this->exif(9),
            'orientation 0' => $this->exif(0),
        ];

        foreach ($broken as $name => $segment) {
            $result = $this->save($this->quartersJpeg($segment), $this->tempDir . '/broken');
            $this->assertSame([40, 20, 'RGBY'], $this->corners($result), $name);
        }
    }

    public function testSaveUploadedImageKeepsTransparency(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesavealpha($image, true);
        ob_start();
        imagepng($image);

        $result = imagecreatefrompng($this->save(ob_get_clean(), $this->tempDir . '/a', 0, 50, 50));
        $this->assertSame(127, imagecolorsforindex($result, imagecolorat($result, 10, 10))['alpha']);
    }

    /**
     * A palette png (a transparent color, not an alpha channel) stays transparent when shrunk.
     */
    public function testSaveUploadedImageKeepsPaletteTransparency(): void
    {
        $image = imagecreate(100, 50);
        imagecolortransparent($image, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);

        $result = imagecreatefrompng($this->save(ob_get_clean(), $this->tempDir . '/a', 0, 50, 50));
        $this->assertSame(127, imagecolorsforindex($result, imagecolorat($result, 10, 10))['alpha']);
    }

    public function testSaveUploadedImageSanitizesSvg(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' .
            '<script>alert(1)</script>' .
            '<rect width="10" height="10" onclick="alert(2)" fill="url(#local)"/>' .
            '<image xlink:href="https://tracker.example.com/pixel.png" width="1" height="1"/>' .
            '<rect width="1" height="1" style="fill:url(https://tracker.example.com/fill)"/>' .
            '</svg>';

        $result = $this->save($svg, $this->tempDir . '/a.svg');
        $saved = file_get_contents($result);

        $this->assertSame($this->tempDir . '/a.svg', $result);
        $this->assertStringContainsString('<rect width="10" height="10" fill="url(#local)"', $saved);
        $this->assertStringNotContainsString('script', $saved);
        $this->assertStringNotContainsString('alert', $saved);
        // remote references would be loaded when the svg is displayed
        $this->assertStringNotContainsString('tracker.example.com', $saved);
    }

    public function testSaveUploadedImageMaxMegapixels(): void
    {
        TestConfig::load(['image_max_megapixels' => 1]);
        $result = $this->save($this->image(1000, 1000, 'png'), $this->tempDir . '/a');
        $this->assertSame($this->tempDir . '/a.png', $result);
        $this->expectExceptionObject(new RuntimeException('The image is larger than the allowed 1 megapixels.'));
        $this->save($this->image(1000, 1001, 'png'), $this->tempDir . '/b');
    }

    public function testSaveUploadedImageInvalidType(): void
    {
        // also for a file that isn't converted
        $this->expectExceptionObject(new InvalidArgumentException('Invalid type: jpeg'));
        $this->save('<svg xmlns="http://www.w3.org/2000/svg"/>', $this->tempDir . '/a', type: 'jpeg');
    }

    public static function saveErrors(): array
    {
        // 'png' is a valid png
        return [
            'too large' => ['png', 10, UPLOAD_ERR_OK, 'The file size exceeds the allowed 10 B.'],
            'upload error' => ['png', 0, UPLOAD_ERR_PARTIAL, 'The file has not been uploaded properly.'],
            'not an image' => ['<?php echo 1;', 0, UPLOAD_ERR_OK, 'This file is not a valid image.'],
            'icon' => ['ico', 0, UPLOAD_ERR_OK, 'This file is not a valid image.'],
            'broken svg' => ['<svg', 0, UPLOAD_ERR_OK, 'This file is not a valid image.'],
            'broken png' => ["\x89PNG\r\n\x1a\n broken", 0, UPLOAD_ERR_OK, 'This file is not a valid image.'],
            // only the header: 20000x20000 would take GD 1.6 GB
            // libjpeg skips the broken segment, getimagesize() doesn't: refused, else GD would decode unchecked sizes
            'zero length EXIF' => ['zero', 0, UPLOAD_ERR_OK, 'This file is not a valid image.'],
            'too many pixels' => ['bomb', 0, UPLOAD_ERR_OK, 'The image is larger than the allowed 25 megapixels.'],
        ];
    }

    #[DataProvider('saveErrors')]
    public function testSaveUploadedImageErrors(string $content, int $maxSize, int $error, string $message): void
    {
        $header = pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
        $bomb = "\x89PNG\r\n\x1a\n" . pack('N', 13) . "IHDR$header" . pack('N', crc32("IHDR$header"));
        $content = ['png' => $this->image(10, 10, 'png'), 'bomb' => $bomb,
            'ico' => file_get_contents(__DIR__ . '/../Fixtures/images/icon.ico'),
            'zero' => $this->quartersJpeg("\xFF\xE1\x00\x00")][$content] ?? $content;
        $this->expectExceptionObject(new RuntimeException($message));

        try {
            $this->save($content, $this->tempDir . '/target', $maxSize, error: $error);
        } finally {
            $this->assertSame([], glob($this->tempDir . '/target*'));
        }
    }
}
