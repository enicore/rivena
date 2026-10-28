<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

/**
 * Small string helpers.
 *
 * @package Enicore\Rivena
 */
class Text
{
    /**
     * Returns the size in bytes as readable text, e.g. "1.5 MB".
     *
     * @param string|int|float $size
     * @return string
     */
    public static function sizeToString(string|int|float $size): string
    {
        if (!is_numeric($size)) {
            return "-";
        }

        $units = ["B", "kB", "MB", "GB", "TB", "PB"];
        $index = 0;

        // rounded, so 999999 is "1 MB", not "1000 kB"
        while (round($size, 1) >= 1000 && $index < count($units) - 1) {
            $size /= 1000;
            $index++;
        }

        return round($size, 1) . " " . $units[$index];
    }

    /**
     * Returns the string cut at a word to the length, with "..." if it was longer.
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    public static function shortenString(string $string, int $length = 50): string
    {
        $string = trim($string);

        if (mb_strlen($string) <= $length) {
            return $string;
        }

        $string = mb_substr($string, 0, $length);

        if ($i = mb_strrpos($string, " ")) {
            $string = mb_substr($string, 0, $i);
        }

        return $string . "...";
    }

    /**
     * Returns the string without the slashes at the end.
     *
     * @param string $string
     * @return string
     */
    public static function removeSlash(string $string): string
    {
        return rtrim($string, '/\\');
    }

    /**
     * Returns the string ending with one slash.
     *
     * @param string $string
     * @return string
     */
    public static function addSlash(string $string): string
    {
        return self::removeSlash($string) . '/';
    }

    /**
     * Returns true if the string is "true".
     *
     * @param string $value
     * @return bool
     */
    public static function strToBool(string $value): bool
    {
        return trim($value) === 'true';
    }
}
