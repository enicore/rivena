<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use DateTime;
use DateTimeZone;
use Exception;
use IntlTimeZone;

/**
 * Time zones with readable names for select lists, e.g. "Germany (+01:00)", and time zone helpers.
 *
 * @package Enicore\Rivena
 */
class Timezones
{
    /**
     * The getTimezones() name formats for countries with one time zone and with several.
     */
    protected static string $singleTimezoneFormat = '%country (%offset)';
    protected static string $multipleTimezoneFormat = '%country / %tz_generic_location (%offset)';

    /**
     * All the time zone IDs by country code; filled on first use (getTimezoneData()).
     */
    protected static array $timezones = [];

    /**
     * The time zones shown for countries with several, instead of all their IDs (Argentina has 12 with the same
     * offset). getTimezones() with $extended shows them all.
     */
    protected static array $essentialTimezones = [
        'AQ' => ['Antarctica/Troll', 'Antarctica/Syowa', 'Antarctica/Mawson', 'Antarctica/Vostok', 'Antarctica/Davis',
            'Antarctica/Casey', 'Antarctica/DumontDUrville', 'Antarctica/McMurdo', 'Antarctica/Palmer'],
        'AR' => ['America/Argentina/Buenos_Aires'],
        'AU' => ['Australia/Perth', 'Australia/Eucla', 'Australia/Darwin', 'Australia/Brisbane', 'Australia/Adelaide',
            'Antarctica/Macquarie', 'Australia/Lord_Howe', 'Australia/Melbourne', 'Australia/Sydney'],
        'BR' => ['America/Noronha', 'America/Araguaina', 'America/Sao_Paulo', 'America/Boa_Vista', 'America/Eirunepe'],
        'CA' => ['America/St_Johns', 'America/Blanc-Sablon', 'America/Atikokan', 'America/Toronto',
            'America/Rankin_Inlet', 'America/Cambridge_Bay', 'America/Edmonton', 'America/Whitehorse',
            'America/Vancouver'],
        'CD' => ['Africa/Kinshasa', 'Africa/Lubumbashi'],
        'CL' => ['America/Punta_Arenas', 'Pacific/Easter'],
        'CN' => ['Asia/Urumqi', 'Asia/Shanghai'],
        'CY' => ['Asia/Famagusta'],
        'DE' => ['Europe/Berlin'],
        'EC' => ['America/Guayaquil', 'Pacific/Galapagos'],
        'ES' => ['Atlantic/Canary', 'Europe/Madrid'],
        'FM' => ['Pacific/Chuuk', 'Pacific/Kosrae'],
        'GL' => ['America/Danmarkshavn', 'America/Nuuk', 'America/Thule'],
        'ID' => ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'],
        'KI' => ['Pacific/Tarawa', 'Pacific/Kanton', 'Pacific/Kiritimati'],
        'KZ' => ['Asia/Almaty'],
        'MH' => ['Pacific/Kwajalein'],
        'MN' => ['Asia/Hovd', 'Asia/Ulaanbaatar'],
        'MX' => ['America/Cancun', 'America/Bahia_Banderas', 'America/Chihuahua', 'America/Ciudad_Juarez',
            'America/Hermosillo', 'America/Tijuana'],
        'MY' => ['Asia/Kuala_Lumpur'],
        'NZ' => ['Pacific/Auckland', 'Pacific/Chatham'],
        'PF' => ['Pacific/Gambier', 'Pacific/Marquesas', 'Pacific/Tahiti'],
        'PG' => ['Pacific/Port_Moresby', 'Pacific/Bougainville'],
        'PS' => ['Asia/Gaza'],
        'PT' => ['Atlantic/Madeira', 'Europe/Lisbon', 'Atlantic/Azores'],
        'RU' => ['Europe/Kaliningrad', 'Europe/Moscow', 'Europe/Samara', 'Asia/Yekaterinburg', 'Asia/Omsk',
            'Asia/Krasnoyarsk', 'Asia/Irkutsk', 'Asia/Yakutsk', 'Asia/Vladivostok', 'Asia/Magadan', 'Asia/Anadyr'],
        'UA' => ['Europe/Kyiv', 'Europe/Simferopol'],
        'UM' => ['Pacific/Wake', 'Pacific/Midway'],
        'US' => ['America/Detroit', 'America/Indiana/Indianapolis', 'America/Kentucky/Louisville', 'America/New_York',
            'America/Chicago', 'America/Boise', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles',
            'America/Anchorage', 'America/Adak', 'Pacific/Honolulu'],
        'UZ' => ['Asia/Samarkand'],
    ];

    /**
     * Returns the time zones as ID => name in the language, UTC first, then sorted by name, e.g. "Germany (+01:00)".
     * The formats can use %country, %offset and the %tz_... names; extended adds every zone of the countries with
     * several.
     *
     * @param bool $extended
     * @param string $language
     * @param string|null $singleTimezoneFormat
     * @param string|null $multipleTimezoneFormat
     * @return array
     */
    public static function getTimezones(bool $extended = false,
                                        string $language = 'en',
                                        ?string $singleTimezoneFormat = null,
                                        ?string $multipleTimezoneFormat = null): array
    {
        $result = [];

        foreach (self::getTimezoneData($extended) as $countryCode => $timezones) {
            foreach ($timezones as $timezone) {
                $result[$timezone] = self::createLocalizedName(
                    $timezone,
                    $language,
                    count($timezones) == 1 ?
                        ($singleTimezoneFormat ?? static::$singleTimezoneFormat) :
                        ($multipleTimezoneFormat ?? static::$multipleTimezoneFormat),
                    $countryCode
                );
            }
        }

        asort($result);

        // not a country's, for those who want the time without a place's (e.g. to compare with logs)
        return ['UTC' => 'UTC'] + $result;
    }

    /**
     * Returns the time zone's name in the language, or its ID if it has no country.
     *
     * @param string $timezone
     * @param string $language
     * @param string|null $format
     * @return string
     */
    public static function getName(string $timezone, string $language = 'en', ?string $format = null): string
    {
        try {
            if (($location = (new DateTimeZone($timezone))->getLocation()) &&
                !empty($location['country_code'])
            ) {
                return self::createLocalizedName(
                    $timezone,
                    $language,
                    $format ?? self::$multipleTimezoneFormat,
                    $location['country_code']
                );
            }
        } catch (Exception) {}

        return $timezone;
    }

    /**
     * Returns the time zone's current UTC offset, e.g. "+01:00".
     *
     * @param string $timezone
     * @return string
     */
    public static function getUtcOffset(string $timezone): string
    {
        try {
            $dtz = new DateTimeZone($timezone);
            $offset = $dtz->getOffset(new DateTime('now', $dtz));

            return sprintf('%+03d:%02d', intdiv($offset, 3600), abs($offset % 3600) / 60);
        } catch (Exception) {
            return 'Invalid Time Zone';
        }
    }

    /**
     * Returns true if the time zone exists.
     *
     * @param string $timezone
     * @return bool
     */
    public static function timeZoneExists(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Returns the date and time converted from one time zone to another (Y-m-d H:i:s), or null if it fails.
     *
     * @param string $dateTime
     * @param string $fromTimezone
     * @param string $toTimezone
     * @return string|null
     */
    public static function convertTime(string $dateTime, string $fromTimezone, string $toTimezone): ?string
    {
        try {
            $fromZone = new DateTimeZone($fromTimezone);
            $toZone = new DateTimeZone($toTimezone);
            $date = new DateTime($dateTime, $fromZone);
            $date->setTimezone($toZone);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Returns the current date and time in the time zone (Y-m-d H:i:s), or null if it fails.
     *
     * @param string $timezone
     * @return string|null
     */
    public static function getCurrentTime(string $timezone): ?string
    {
        try {
            $dateTime = new DateTime('now', new DateTimeZone($timezone));
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Returns the country's time zone IDs (the code in any letter case).
     *
     * @param string $countryCode
     * @param bool $extended
     * @return array
     */
    public static function getTimezonesByCountry(string $countryCode, bool $extended = false): array
    {
        $timezoneData = self::getTimezoneData($extended);
        return $timezoneData[strtoupper($countryCode)] ?? [];
    }

    /**
     * Returns the time zone IDs grouped by country code.
     *
     * @param bool $extended
     * @return array
     */
    public static function getTimezoneData(bool $extended = false): array
    {
        if (empty(self::$timezones)) {
            foreach (DateTimeZone::listIdentifiers() as $id) {
                try {
                    $tz = new DateTimeZone($id);
                    // UTC has the country code "??"
                    if (($location = $tz->getLocation()) && isset($location['country_code']) &&
                        $location['country_code'] !== '??'
                    ) {
                        self::$timezones[$location['country_code']][] = $id;
                    }
                } catch (Exception) {}
            }
        }

        return $extended ? self::$timezones : array_merge(self::$timezones, self::$essentialTimezones);
    }

    /**
     * Returns the format with the time zone's placeholders filled in, or its ID if it fails.
     *
     * @param string $timezone
     * @param string $language
     * @param string $format
     * @param string $countryCode
     * @return string
     */
    protected static function createLocalizedName(string $timezone, string $language, string $format,
                                                 string $countryCode): string
    {
        if (!($itz = IntlTimeZone::createTimeZone($timezone)) ||
            !($countryName = Countries::getName($countryCode, $language))
        ) {
            return $timezone;
        }

        $map = ['%country' => $countryName, '%offset' => self::getUtcOffset($timezone)];
        $keys = [
            'tz_short' => IntlTimeZone::DISPLAY_SHORT,
            'tz_long' => IntlTimeZone::DISPLAY_LONG,
            'tz_short_generic' => IntlTimeZone::DISPLAY_SHORT_GENERIC,
            'tz_long_generic' => IntlTimeZone::DISPLAY_LONG_GENERIC,
            'tz_short_gmt' => IntlTimeZone::DISPLAY_SHORT_GMT,
            'tz_long_gmt' => IntlTimeZone::DISPLAY_LONG_GMT,
            'tz_short_commonly_used' => IntlTimeZone::DISPLAY_SHORT_COMMONLY_USED,
            'tz_generic_location' => IntlTimeZone::DISPLAY_GENERIC_LOCATION,
        ];

        foreach ($keys as $key => $value) {
            $map["%$key"] = $itz->getDisplayName(false, $value, $language);
        }

        // strtr() replaces the longest placeholder first (%tz_short_gmt, not %tz_short), str_replace() in order
        return strtr($format, $map);
    }
}
