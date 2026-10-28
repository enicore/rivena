<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Locale;

/**
 * Country codes (ISO 3166-1 alpha-2) and their names in any language (PHP's Intl).
 *
 * @package Enicore\Rivena
 */
class Countries
{
    // ISO 3166-1 alpha-2 country codes
    protected static array $countryCodes = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ', 'BA', 'BB',
        'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY',
        'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW', 'CX',
        'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'EU', 'FI', 'FJ',
        'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR',
        'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ',
        'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ',
        'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH',
        'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA', 'NC',
        'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL',
        'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE',
        'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD',
        'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US',
        'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'XK', 'YE', 'YT', 'ZA', 'ZM', 'ZW'
    ];

    /**
     * Returns the countries (all, or the given codes) as code => name, in the language.
     *
     * @param array $codes
     * @param string $language
     * @return array
     */
    public static function getCountries(array $codes = [], string $language = 'en'): array
    {
        $codes = empty($codes) ? self::$countryCodes : array_map('strtoupper', $codes);
        $result = [];

        foreach ($codes as $code) {
            if (in_array($code, self::$countryCodes, true) &&
                ($name = Locale::getDisplayRegion('-' . $code, $language))
            ) {
                $result[$code] = $name;
            }
        }

        return $result;
    }

    /**
     * Returns all the country codes (ISO 3166-1 alpha-2).
     *
     * @return array
     */
    public static function getCodes(): array
    {
        return self::$countryCodes;
    }

    /**
     * Returns the country's name in the language, or "" if the code is unknown.
     *
     * @param string $code
     * @param string $language
     * @return string
     */
    public static function getName(string $code, string $language = 'en'): string
    {
        if (empty($code)) {
            return '';
        }

        $code = strtoupper($code);
        $result = Locale::getDisplayRegion('-' . $code, $language);

        return $result == $code ? '' : $result;
    }

    /**
     * Returns true if the country code exists.
     *
     * @param string $countryCode
     * @return bool
     */
    public static function codeExists(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::$countryCodes);
    }
}