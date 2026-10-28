<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

/**
 * Language codes with their English and local names.
 *
 * @package Enicore\Rivena
 */
class Languages
{
    const int ENGLISH_INDEX = 0;
    const int LOCALIZED_INDEX = 1;

    /**
     * Language codes (ISO 639-1) => [English name, local name].
     */
    protected static array $languages = [
        'af' => ['Afrikaans', 'Afrikaans'],
        'am' => ['Amharic', 'አማርኛ'],
        'ar' => ['Arabic', 'العربية'],
        'az' => ['Azerbaijani', 'Azərbaycanca'],
        'be' => ['Belarusian', 'Беларуская'],
        'bg' => ['Bulgarian', 'Български'],
        'bn' => ['Bengali', 'বাংলা'],
        'bs' => ['Bosnian', 'Bosanski'],
        'ca' => ['Catalan', 'Català'],
        'cs' => ['Czech', 'Čeština'],
        'cy' => ['Welsh', 'Cymraeg'],
        'da' => ['Danish', 'Dansk'],
        'de' => ['German', 'Deutsch'],
        'el' => ['Greek', 'Ελληνικά'],
        'en' => ['English', 'English'],
        'es' => ['Spanish', 'Español'],
        'et' => ['Estonian', 'Eesti'],
        'eu' => ['Basque', 'Euskara'],
        'fa' => ['Persian', 'فارسی'],
        'fi' => ['Finnish', 'Suomi'],
        'fr' => ['French', 'Français'],
        'ga' => ['Irish', 'Gaeilge'],
        'gl' => ['Galician', 'Galego'],
        'gu' => ['Gujarati', 'ગુજરાતી'],
        'he' => ['Hebrew', 'עברית'],
        'hi' => ['Hindi', 'हिंदी'],
        'hr' => ['Croatian', 'Hrvatski'],
        'hu' => ['Hungarian', 'Magyar'],
        'hy' => ['Armenian', 'Հայերեն'],
        'id' => ['Indonesian', 'Bahasa Indonesia'],
        'is' => ['Icelandic', 'Íslenska'],
        'it' => ['Italian', 'Italiano'],
        'ja' => ['Japanese', '日本語'],
        'ka' => ['Georgian', 'ქართული'],
        'kk' => ['Kazakh', 'Қазақ'],
        'km' => ['Khmer', 'ខ្មែរ'],
        'kn' => ['Kannada', 'ಕನ್ನಡ'],
        'ko' => ['Korean', '한국어'],
        'lo' => ['Lao', 'ລາວ'],
        'lt' => ['Lithuanian', 'Lietuvių'],
        'lv' => ['Latvian', 'Latviešu'],
        'mk' => ['Macedonian', 'Македонски'],
        'ml' => ['Malayalam', 'മലയാളം'],
        'mn' => ['Mongolian', 'Монгол'],
        'mr' => ['Marathi', 'मराठी'],
        'ms' => ['Malay', 'Bahasa Melayu'],
        'mt' => ['Maltese', 'Malti'],
        'my' => ['Burmese', 'မြန်မာ'],
        'ne' => ['Nepali', 'नेपाली'],
        'nl' => ['Dutch', 'Nederlands'],
        'no' => ['Norwegian', 'Norsk'],
        'or' => ['Odia', 'ଓଡ଼ିଆ'],
        'pa' => ['Punjabi', 'ਪੰਜਾਬੀ'],
        'pl' => ['Polish', 'Polski'],
        'pt' => ['Portuguese', 'Português'],
        'ro' => ['Romanian', 'Română'],
        'ru' => ['Russian', 'Русский'],
        'si' => ['Sinhala', 'සිංහල'],
        'sk' => ['Slovak', 'Slovenčina'],
        'sl' => ['Slovenian', 'Slovenščina'],
        'sq' => ['Albanian', 'Shqip'],
        'sr' => ['Serbian', 'Српски'],
        'sv' => ['Swedish', 'Svenska'],
        'sw' => ['Swahili', 'Kiswahili'],
        'ta' => ['Tamil', 'தமிழ்'],
        'te' => ['Telugu', 'తెలుగు'],
        'th' => ['Thai', 'ไทย'],
        'tr' => ['Turkish', 'Türkçe'],
        'uk' => ['Ukrainian', 'Українська'],
        'ur' => ['Urdu', 'اردو'],
        'uz' => ['Uzbek', 'O‘zbek'],
        'vi' => ['Vietnamese', 'Tiếng Việt'],
        'zh' => ['Chinese', '中文'],
    ];

    /**
     * Returns the languages (all, or the given codes) as code => name, with English or local names.
     *
     * @param array $codes
     * @param bool $useLocalizedNames
     * @return array
     */
    public static function getLanguages(array $codes = [], bool $useLocalizedNames = false): array
    {
        $codes = empty($codes) ? array_keys(self::$languages) : array_map('strtolower', $codes);
        $result = [];

        foreach ($codes as $code) {
            if (isset(self::$languages[$code])) {
                $result[$code] = $useLocalizedNames ? self::$languages[$code][self::LOCALIZED_INDEX] :
                    self::$languages[$code][self::ENGLISH_INDEX];
            }
        }

        return $result;
    }

    /**
     * Returns all the language codes.
     *
     * @return array
     */
    public static function getCodes(): array
    {
        return array_keys(self::$languages);
    }

    /**
     * Returns the language's English or local name, or false if the code is unknown.
     *
     * @param string $code
     * @param bool $useLocalizedNames
     * @return string|false
     */
    public static function getName(string $code, bool $useLocalizedNames = false): string|false
    {
        $code = strtolower($code);

        if (!isset(self::$languages[$code])) {
            return false;
        }

        return $useLocalizedNames ? self::$languages[$code][self::LOCALIZED_INDEX] :
            self::$languages[$code][self::ENGLISH_INDEX];
    }

    /**
     * Returns true if the language code exists.
     *
     * @param string $code
     * @return bool
     */
    public static function codeExists(string $code): bool
    {
        return array_key_exists(strtolower($code), self::$languages);
    }
}
