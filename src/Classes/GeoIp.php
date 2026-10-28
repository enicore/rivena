<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use MaxMind\Db\Reader;
use Throwable;

/**
 * Finds the country of an IP address in a local database (src/resources/geoip, updated by bin/build.sh), without
 * requests:
 *
 * GeoIp::country('81.2.69.142');      // "GB"
 * GeoIp::countryName('81.2.69.142');  // "United Kingdom"
 *
 * @package Enicore\Rivena
 */
final class GeoIp
{
    private static ?Reader $reader = null;

    /**
     * Returns the country code of the IP address, e.g. "GB", or null for private and unknown addresses.
     *
     * @param string $ip
     * @return string|null
     */
    public static function country(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        try {
            self::$reader ??= new Reader(dirname(__DIR__) . '/resources/geoip/geo-whois-asn-country.mmdb');
            $record = self::$reader->get($ip);
        } catch (Throwable $e) {
            error_log("GeoIp: {$e->getMessage()}");
            return null;
        }

        $code = $record['country_code'] ?? null;

        return is_string($code) && preg_match('/^[A-Za-z]{2}$/', $code) ? strtoupper($code) : null;
    }

    /**
     * Returns the country name of the IP address, e.g. "United Kingdom", or null.
     *
     * @param string $ip
     * @param string $language
     * @return string|null
     */
    public static function countryName(string $ip, string $language = 'en'): ?string
    {
        $code = self::country($ip);
        return $code === null ? null : (Countries::getName($code, $language) ?: $code);
    }
}
