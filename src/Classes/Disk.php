<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use enshrined\svgSanitize\Sanitizer;
use finfo;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Random\RandomException;
use RuntimeException;
use ValueError;

/**
 * File and directory helpers: paths, file names, removing directories, saving uploaded images.
 *
 * @package Enicore\Rivena
 */
class Disk
{
    /**
     * Returns a subdirectory path for the ID, e.g. "000/001/", so no directory gets too many files.
     *
     * @param string|int $id
     * @param int $idsPerDir
     * @param int $levels
     * @return string
     */
    public static function getStructuredDirectory(string|int $id, int $idsPerDir = 500, int $levels = 2): string
    {
        if ($idsPerDir <= 0) {
            $idsPerDir = 100;
        }

        if ($levels < 1 || $levels > 3) {
            $levels = 2;
        }

        $level1 = floor($id / $idsPerDir);
        $level2 = floor($level1 / 1000);
        $level3 = floor($level2 / 1000);

        return ($levels > 2 ? sprintf("%03d", $level3 % 1000) . "/" : "") .
            ($levels > 1 ? sprintf("%03d", $level2 % 1000) . "/" : "") .
            sprintf("%03d", $level1 % 1000) . "/";
    }

    /**
     * Returns the string as a safe file name (without leading or trailing dots, control characters, at most 255 bytes,
     * the filesystems' limit, keeping the extension), or "unknown" if nothing is left.
     *
     * @param string $string
     * @return string
     */
    public static function ensureFileName(string $string): string
    {
        $result = preg_replace(["/[\/\\\:?*+%|\"<>\x00-\x1F\x7F]/", "/_{2,}/"], "_", strtolower($string));
        // no leading dots: ".." and hidden files like .htaccess aren't safe names
        $result = trim($result, "._ ");

        if (strlen($result) > 255) {
            $extension = strrchr($result, '.');
            $extension = $extension !== false && strlen($extension) <= 20 ? $extension : '';
            // not in the middle of a UTF-8 character
            $result = trim(mb_strcut($result, 0, 255 - strlen($extension), 'UTF-8'), "._ ") . $extension;
        }

        return $result !== '' ? $result : "unknown";
    }

    /**
     * Returns a random file name that doesn't exist in the directory.
     *
     * @param string $path
     * @param string $ext
     * @param string $prefix
     * @return string
     * @throws RandomException
     */
    public static function getUniqueFileName(string $path, string $ext = '', string $prefix = ''): string
    {
        if ($ext !== '' && $ext[0] !== '.') {
            $ext = "." . $ext;
        }

        $path = rtrim($path, '/');

        do {
            $fileName = $prefix . bin2hex(random_bytes(16)) . $ext;
        } while (file_exists($path . '/' . $fileName));

        return $fileName;
    }

    /**
     * Returns the file's extension in lowercase.
     *
     * @param string $fileName
     * @return string
     */
    public static function getExtension(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * Creates the directory (0755, with umask); returns true if it exists or was created and is writable.
     *
     * @param string $dir
     * @return bool
     */
    public static function makeDir(string $dir): bool
    {
        if (file_exists($dir)) {
            return is_writable($dir);
        }

        return @mkdir($dir, 0755, true);
    }

    /**
     * Removes the directory with everything in it, or only its contents. Links are removed, not followed, unless
     * followLinks is true; a link given as the directory too (with contentsOnly, it's left as is and false returned).
     *
     * @param string $dir
     * @param bool $followLinks
     * @param bool $contentsOnly
     * @return bool
     */
    public static function removeDir(string $dir, bool $followLinks = false, bool $contentsOnly = false): bool
    {
        if (empty($dir) || !is_dir($dir)) {
            return true;
        }

        // is_link() is false with the trailing slash, which follows the link
        $link = rtrim($dir, '/\\');

        if (is_link($link)) {
            if (!$followLinks) {
                return !$contentsOnly && unlink($link);
            }

            return self::removeDir(realpath($link), true, $contentsOnly) && ($contentsOnly || unlink($link));
        }

        $dir = Text::addSlash($dir);
        $files = array_diff(scandir($dir), [".", ".."]);

        foreach ($files as $file) {
            // check links first: is_dir() is true for a link to a directory, which must not be emptied unless following
            if (is_link($dir . $file)) {
                if ($followLinks && ($target = realpath($dir . $file))) {
                    is_dir($target) ? self::removeDir($target, true) : unlink($target);
                }

                unlink($dir . $file);
                continue;
            }

            if (is_dir($dir . $file)) {
                self::removeDir($dir . $file, $followLinks);
                continue;
            }

            unlink($dir . $file);
        }

        return $contentsOnly || rmdir($dir);
    }

    /**
     * Deletes the directory's files older than the interval (seconds); returns false if the directory can't be opened.
     *
     * @param string $directory
     * @param string|int $interval
     * @return bool
     */
    public static function deleteOutdatedFiles(string $directory, string|int $interval): bool
    {
        // remove trailing slash
        $directory = rtrim($directory, '/\\');

        if (!is_dir($directory) || !$handle = opendir($directory)) {
            return false;
        }

        while (($file = readdir($handle)) !== false) {
            if (is_file($directory . '/' . $file) && filemtime($directory . '/' . $file) < time() - $interval) {
                unlink($directory . '/' . $file);
            }
        }

        closedir($handle);
        return true;
    }

    /**
     * Returns PHP's upload limit in bytes (the smaller of post_max_size and upload_max_filesize), or 0 for none.
     *
     * @return float|int
     */
    public static function getMaxFileUploadSize(): float|int
    {
        $bytes = 0;

        if (($b = self::sizeToBytes(ini_get('post_max_size'))) > 0) {
            $bytes = $b;
        }

        if (($b = self::sizeToBytes(ini_get('upload_max_filesize'))) > 0 && (!$bytes || $b < $bytes)) {
            $bytes = $b;
        }

        return $bytes;
    }

    /**
     * Returns a php.ini size like "64M" in bytes.
     *
     * @param string $size
     * @return int
     */
    public static function sizeToBytes(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9.]/', '', $size); // Remove the non-numeric characters from the size.

        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return (int)round((float)$size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }

        return (int)round((float)$size);
    }

    /**
     * Returns the file's MIME type from its contents ("image/svg" for all svgs).
     *
     * @param string $filePath
     * @return string
     */
    public static function getFileMimeType(string $filePath): string
    {
        $type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filePath);

        // it could be "image/svg+xml" so we're simplifying
        if (str_starts_with($type, "image/svg")) {
            return "image/svg";
        }

        // icon could also be this
        if ($type == "image/vnd.microsoft.icon") {
            return "image/x-icon";
        }

        return $type;
    }

    /**
     * Saves the uploaded image (jpg, png, gif or svg) as the target with the image type's extension, and returns its
     * path. Images are re-saved, so nothing hidden in the file is kept (svgs are cleaned, animated gifs keep their first
     * frame), and shrunk to the max size; type ("jpg", "png" or "gif") saves jpgs, pngs and gifs as that type. Images
     * over the config's image_max_megapixels are refused.
     *
     * @param UploadedFileInterface $file
     * @param string $target
     * @param int $maxSize
     * @param int $maxWidth
     * @param int $maxHeight
     * @param string $type
     * @return string
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function saveUploadedImage(UploadedFileInterface $file, string $target, int $maxSize = 0,
                                             int $maxWidth = 0, int $maxHeight = 0, string $type = ''): string
    {
        // the code's mistake, not the user's: not a RuntimeException, whose message the user sees
        if (!in_array($type, ['', 'jpg', 'png', 'gif'], true)) {
            throw new InvalidArgumentException("Invalid type: $type");
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The file has not been uploaded properly.');
        }

        if ($maxSize && $file->getSize() > $maxSize) {
            throw new RuntimeException('The file size exceeds the allowed ' . Text::sizeToString($maxSize) . '.');
        }

        $data = (string)$file->getStream();
        $extension = match (new finfo(FILEINFO_MIME_TYPE)->buffer($data)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/svg+xml', 'image/svg' => 'svg',
            default => throw new RuntimeException('This file is not a valid image.'),
        };

        if ($extension === 'svg') {
            // remote references would be loaded when the svg is shown (tracking, requests to other hosts)
            $sanitizer = new Sanitizer();
            $sanitizer->removeRemoteReferences(true);
            try {
                $data = $sanitizer->sanitize($data);
            } catch (ValueError) {
                $data = false; // svg-sanitize 1.1.0 throws on some broken svgs (strpos() past the end)
            }

            $data = $data ?: throw new RuntimeException('This file is not a valid image.');
        } else {
            // a small file can have huge dimensions, and GD's memory isn't limited by memory_limit: check them first
            $size = @getimagesizefromstring($data) ?: throw new RuntimeException('This file is not a valid image.');

            $maxMegapixels = Config::get('image_max_megapixels');

            if ($size[0] * $size[1] > $maxMegapixels * 1_000_000) {
                throw new RuntimeException("The image is larger than the allowed $maxMegapixels megapixels.");
            }

            $image = @imagecreatefromstring($data) ?: throw new RuntimeException('This file is not a valid image.');

            // phones save photos unturned, with the rotation in the EXIF data, which isn't saved: apply it
            if ($extension === 'jpg') {
                $orientation = self::getJpegOrientation($data);
                $angle = [3 => 180, 5 => 270, 6 => 270, 7 => 270, 8 => 90][$orientation] ?? 0;
                $flip = [2 => IMG_FLIP_HORIZONTAL, 4 => IMG_FLIP_VERTICAL, 5 => IMG_FLIP_HORIZONTAL,
                    7 => IMG_FLIP_VERTICAL][$orientation] ?? 0;

                if ($angle) {
                    $image = imagerotate($image, $angle, 0);
                }

                if ($flip) {
                    imageflip($image, $flip);
                }
            }

            $scale = min(1, $maxWidth ? $maxWidth / imagesx($image) : 1, $maxHeight ? $maxHeight / imagesy($image) : 1);

            if ($scale < 1) {
                // not imagescale(), which loses the transparency
                $width = (int)round(imagesx($image) * $scale);
                $height = (int)round(imagesy($image) * $scale);
                $scaled = imagecreatetruecolor($width, $height);
                imagealphablending($scaled, false);
                imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
                $image = $scaled;
            }

            $extension = $type ?: $extension;

            // jpgs have no transparency: white instead of black
            if ($extension === 'jpg') {
                $background = imagecreatetruecolor(imagesx($image), imagesy($image));
                imagefill($background, 0, 0, imagecolorallocate($background, 255, 255, 255));
                imagecopy($background, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                $image = $background;
            }

            imagesavealpha($image, true);
            ob_start();
            match ($extension) {
                'jpg' => imagejpeg($image, null, 75),
                'png' => imagepng($image, null, 9),
                'gif' => imagegif($image),
            };
            $data = ob_get_clean();
        }

        $target = preg_replace('/\.(jpeg|jpg|png|gif|svg)$/i', '', $target) . ".$extension";

        if (!self::makeDir(dirname($target)) || file_put_contents($target, $data) === false) {
            throw new RuntimeException("Can't save the image.");
        }

        return $target;
    }

    /**
     * Returns the jpg's EXIF orientation (1-8 if it's valid), or 1 if it has none or it can't be read.
     *
     * @param string $data
     * @return int
     */
    private static function getJpegOrientation(string $data): int
    {
        $offset = 2;

        // the segments before the image data (FF DA): FF, the type, the length (with its 2 bytes), the data
        while ($offset + 4 <= strlen($data) && $data[$offset] === "\xFF" && $data[$offset + 1] !== "\xDA") {
            $length = unpack('n', $data, $offset + 2)[1];

            // EXIF (FF E1): a TIFF header with the byte order and the offset of the first list of 12-byte entries
            if ($data[$offset + 1] === "\xE1" && substr($data, $offset + 4, 6) === "Exif\0\0") {
                $tiff = substr($data, $offset + 10, $length - 8);
                [$short, $long] = str_starts_with($tiff, 'MM') ? ['n', 'N'] : ['v', 'V'];

                if (strlen($tiff) < 8) {
                    return 1;
                }

                $list = unpack($long, $tiff, 4)[1];
                $count = $list + 2 <= strlen($tiff) ? unpack($short, $tiff, $list)[1] : 0;

                for ($i = 0; $i < $count && $list + 14 + $i * 12 <= strlen($tiff); $i++) {
                    $entry = $list + 2 + $i * 12;

                    // the orientation's tag (0112), with its value in the first 2 bytes of the entry's last 4
                    if (unpack($short, $tiff, $entry)[1] === 0x0112) {
                        return unpack($short, $tiff, $entry + 8)[1];
                    }
                }

                return 1;
            }

            $offset += 2 + $length;
        }

        return 1;
    }
}
