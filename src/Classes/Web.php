<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;


/**
 * URL and HTML helpers: the site's URL, versioned asset URLs and tags, slugs, the browser's language, email checks.
 *
 * @package Enicore\Rivena
 */
class Web
{
    /**
     * Returns the site's full URL (site_url in the config) with the path, e.g. "https://example.com/adm/users".
     *
     * @param string $path
     * @return string
     */
    public static function getUrl(string $path = ''): string
    {
        // not the request's host: a client can send any, e.g. to get emailed links to another site
        return rtrim(Config::get('site_url'), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Returns the text as a URL slug, e.g. "hello-world" for "Hello World!", or false if nothing is left (empty text,
     * only symbols).
     *
     * @param string $text
     * @return bool|string
     */
    public static function createPermalink(string $text): bool|string
    {
        // the separators collapsed before trimming, so none is left at the ends ("emoji 😀" is "emoji- " first)
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        $text = preg_replace("/[\/_|+ -]+/", "-", preg_replace("%[^-/+|\w ]%", "-", $text));
        $text = strtolower(trim($text, "-"));

        return $text === '' ? false : $text;
    }

    /**
     * Returns the browser's preferred language from Accept-Language (the highest q, the first of equal ones), e.g.
     * "en-us"; "en" if there's no valid one (other entries are skipped).
     *
     * @return string
     */
    public static function getBrowserLanguage(): string
    {
        $result = 'en';
        $best = 0.0;

        foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $entry) {
            // a language tag ("de", "en-US", "zh-Hant-TW") with an optional q (1 without it)
            if (preg_match('/^\s*([a-z]{1,8}(?:-[a-z0-9]{1,8})*)\s*(?:;\s*q\s*=\s*([01](?:\.\d{0,3})?))?\s*$/i', $entry,
                    $matches) && ($q = (float)($matches[2] ?? 1)) > $best) {
                $result = strtolower($matches[1]);
                $best = $q;
            }
        }

        return $result;
    }

    /**
     * Returns true if the email address is valid and, unless checkDns is false, its domain exists.
     *
     * @param string $email
     * @param bool $checkDns
     * @return bool
     */
    public static function validateEmail(string $email, bool $checkDns = true): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($checkDns) {
            $array = explode("@", $email);
            $domain = end($array);

            // check the mx record (dot at the end, so it doesn't get treated as a subdomain of the local domain)
            if (!checkdnsrr($domain . ".")) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the asset URL with its version appended as ?v=, e.g. "/img/logo.png?v=3f2a..."; the version is a hash of
     * the file's modification time.
     *
     * @param string $path
     * @return string
     */
    public static function versionedUrl(string $path): string
    {
        // hashed, so the file's time isn't shown
        return $path . '?v=' . md5((string)filemtime(dirname($_SERVER['SCRIPT_FILENAME']) . '/' . ltrim($path, '/')));
    }

    /**
     * Returns a hash of the modification times of public/js/app.js and public/css/app.css.
     *
     * @return string
     */
    public static function assetsVersion(): string
    {
        $public = dirname($_SERVER['SCRIPT_FILENAME']);
        return md5(filemtime("$public/js/app.js") . filemtime("$public/css/app.css"));
    }

    /**
     * Returns a <script> tag for the asset URL with its version appended as ?v=.
     *
     * @param string $path
     * @param array $attr
     * @return string
     */
    public static function scriptTag(string $path, array $attr = []): string
    {
        return "<script src='" . htmlspecialchars(self::versionedUrl($path), ENT_QUOTES) . "'" . self::attr($attr) .
            "></script>";
    }

    /**
     * Returns a stylesheet <link> tag for the asset URL with its version appended as ?v=.
     *
     * @param string $path
     * @param array $attr
     * @return string
     */
    public static function styleTag(string $path, array $attr = []): string
    {
        return "<link rel='stylesheet' href='" . htmlspecialchars(self::versionedUrl($path), ENT_QUOTES) . "'" .
            self::attr($attr) . ">";
    }

    /**
     * Returns an icon <link> tag for the asset URL with its version appended as ?v=.
     *
     * @param string $path
     * @param array $attr
     * @return string
     */
    public static function iconTag(string $path, array $attr = []): string
    {
        return "<link rel='icon' href='" . htmlspecialchars(self::versionedUrl($path), ENT_QUOTES) . "'" .
            self::attr($attr) . ">";
    }

    /**
     * Returns an <img> tag for the asset URL with its version appended as ?v=.
     *
     * @param string $path
     * @param array $attr
     * @return string
     */
    public static function imageTag(string $path, array $attr = []): string
    {
        return "<img src='" . htmlspecialchars(self::versionedUrl($path), ENT_QUOTES) . "'" . self::attr($attr) . ">";
    }

    /**
     * Returns the attributes as an HTML string: true gives just the name, false and null are left out.
     *
     * @param array $attr
     * @return string
     */
    private static function attr(array $attr): string
    {
        $result = '';
        foreach ($attr as $k => $v) {
            if ($v === null) {
                continue;
            }

            if (is_bool($v)) {
                if ($v) {
                    $result .= ' ' . htmlspecialchars($k, ENT_QUOTES);
                }
                continue;
            }
            $result .= ' ' . htmlspecialchars($k, ENT_QUOTES) . "='" . htmlspecialchars((string)$v, ENT_QUOTES) . "'";
        }
        return $result;
    }
}