<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

/**
 * Locale codes (e.g. en_US) with their English and local names.
 *
 * @package Enicore\Rivena
 */
class Locales
{
    const int ENGLISH_INDEX = 0;
    const int LOCALIZED_INDEX = 1;

    /**
     * Locale codes (e.g. en_US) => [English name, local name].
     */
    protected static array $locales = [
        'af_ZA' => ['Afrikaans', 'Afrikaans'],
        'am_ET' => ['Amharic', 'አማርኛ'],
        'ar_AE' => ['Arabic (United Arab Emirates)', 'العربية (الإمارات العربية المتحدة)'],
        'ar_BH' => ['Arabic (Bahrain)', 'العربية (البحرين)'],
        'ar_DZ' => ['Arabic (Algeria)', 'العربية (الجزائر)'],
        'ar_EG' => ['Arabic (Egypt)', 'العربية (مصر)'],
        'ar_IQ' => ['Arabic (Iraq)', 'العربية (العراق)'],
        'ar_JO' => ['Arabic (Jordan)', 'العربية (الأردن)'],
        'ar_KW' => ['Arabic (Kuwait)', 'العربية (الكويت)'],
        'ar_LB' => ['Arabic (Lebanon)', 'العربية (لبنان)'],
        'ar_LY' => ['Arabic (Libya)', 'العربية (ليبيا)'],
        'ar_MA' => ['Arabic (Morocco)', 'العربية (المغرب)'],
        'ar_OM' => ['Arabic (Oman)', 'العربية (عمان)'],
        'ar_QA' => ['Arabic (Qatar)', 'العربية (قطر)'],
        'ar_SA' => ['Arabic (Saudi Arabia)', 'العربية (السعودية)'],
        'ar_SD' => ['Arabic (Sudan)', 'العربية (السودان)'],
        'ar_SY' => ['Arabic (Syria)', 'العربية (سوريا)'],
        'ar_TN' => ['Arabic (Tunisia)', 'العربية (تونس)'],
        'ar_YE' => ['Arabic (Yemen)', 'العربية (اليمن)'],
        'az_AZ' => ['Azerbaijani', 'Azərbaycanca'],
        'be_BY' => ['Belarusian', 'Беларуская'],
        'bg_BG' => ['Bulgarian', 'Български'],
        'bn_BD' => ['Bengali (Bangladesh)', 'বাংলা (বাংলাদেশ)'],
        'bn_IN' => ['Bengali (India)', 'বাংলা (ভারত)'],
        'bs_BA' => ['Bosnian', 'Bosanski'],
        'ca_ES' => ['Catalan', 'Català'],
        'cs_CZ' => ['Czech', 'Čeština'],
        'cy_GB' => ['Welsh', 'Cymraeg'],
        'da_DK' => ['Danish', 'Dansk'],
        'de_AT' => ['German (Austria)', 'Deutsch (Österreich)'],
        'de_CH' => ['German (Switzerland)', 'Deutsch (Schweiz)'],
        'de_DE' => ['German (Germany)', 'Deutsch (Deutschland)'],
        'el_GR' => ['Greek', 'Ελληνικά'],
        'en_AU' => ['English (Australia)', 'English (Australia)'],
        'en_CA' => ['English (Canada)', 'English (Canada)'],
        'en_GB' => ['English (United Kingdom)', 'English (UK)'],
        'en_IE' => ['English (Ireland)', 'English (Ireland)'],
        'en_IN' => ['English (India)', 'English (India)'],
        'en_NZ' => ['English (New Zealand)', 'English (NZ)'],
        'en_US' => ['English (United States)', 'English (US)'],
        'en_ZA' => ['English (South Africa)', 'English (SA)'],
        'es_AR' => ['Spanish (Argentina)', 'Español (Argentina)'],
        'es_BO' => ['Spanish (Bolivia)', 'Español (Bolivia)'],
        'es_CL' => ['Spanish (Chile)', 'Español (Chile)'],
        'es_CO' => ['Spanish (Colombia)', 'Español (Colombia)'],
        'es_CR' => ['Spanish (Costa Rica)', 'Español (Costa Rica)'],
        'es_DO' => ['Spanish (Dominican Republic)', 'Español (República Dominicana)'],
        'es_EC' => ['Spanish (Ecuador)', 'Español (Ecuador)'],
        'es_ES' => ['Spanish (Spain)', 'Español (España)'],
        'es_GT' => ['Spanish (Guatemala)', 'Español (Guatemala)'],
        'es_HN' => ['Spanish (Honduras)', 'Español (Honduras)'],
        'es_MX' => ['Spanish (Mexico)', 'Español (México)'],
        'es_NI' => ['Spanish (Nicaragua)', 'Español (Nicaragua)'],
        'es_PA' => ['Spanish (Panama)', 'Español (Panamá)'],
        'es_PE' => ['Spanish (Peru)', 'Español (Perú)'],
        'es_PR' => ['Spanish (Puerto Rico)', 'Español (Puerto Rico)'],
        'es_PY' => ['Spanish (Paraguay)', 'Español (Paraguay)'],
        'es_SV' => ['Spanish (El Salvador)', 'Español (El Salvador)'],
        'es_US' => ['Spanish (United States)', 'Español (EE. UU.)'],
        'es_UY' => ['Spanish (Uruguay)', 'Español (Uruguay)'],
        'es_VE' => ['Spanish (Venezuela)', 'Español (Venezuela)'],
        'et_EE' => ['Estonian', 'Eesti'],
        'eu_ES' => ['Basque (Spain)', 'Euskara (Espainia)'],
        'fa_IR' => ['Persian', 'فارسی'],
        'fi_FI' => ['Finnish', 'Suomi'],
        'fr_BE' => ['French (Belgium)', 'Français (Belgique)'],
        'fr_CA' => ['French (Canada)', 'Français (Canada)'],
        'fr_CH' => ['French (Switzerland)', 'Français (Suisse)'],
        'fr_FR' => ['French (France)', 'Français (France)'],
        'ga_IE' => ['Irish', 'Gaeilge'],
        'gl_ES' => ['Galician (Spain)', 'Galego (España)'],
        'gu_IN' => ['Gujarati', 'ગુજરાતી'],
        'he_IL' => ['Hebrew', 'עברית'],
        'hi_IN' => ['Hindi', 'हिंदी'],
        'hr_HR' => ['Croatian', 'Hrvatski'],
        'hu_HU' => ['Hungarian', 'Magyar'],
        'hy_AM' => ['Armenian', 'Հայերեն'],
        'id_ID' => ['Indonesian', 'Bahasa Indonesia'],
        'is_IS' => ['Icelandic', 'Íslenska'],
        'it_CH' => ['Italian (Switzerland)', 'Italiano (Svizzera)'],
        'it_IT' => ['Italian (Italy)', 'Italiano (Italia)'],
        'ja_JP' => ['Japanese', '日本語'],
        'ka_GE' => ['Georgian', 'ქართული'],
        'kk_KZ' => ['Kazakh', 'Қазақ'],
        'km_KH' => ['Khmer', 'ខ្មែរ'],
        'kn_IN' => ['Kannada', 'ಕನ್ನಡ'],
        'ko_KR' => ['Korean', '한국어'],
        'lo_LA' => ['Lao', 'ລາວ'],
        'lt_LT' => ['Lithuanian', 'Lietuvių'],
        'lv_LV' => ['Latvian', 'Latviešu'],
        'mk_MK' => ['Macedonian', 'Македонски'],
        'ml_IN' => ['Malayalam', 'മലയാളം'],
        'mn_MN' => ['Mongolian', 'Монгол'],
        'mr_IN' => ['Marathi', 'मराठी'],
        'ms_MY' => ['Malay', 'Bahasa Melayu'],
        'mt_MT' => ['Maltese', 'Malti'],
        'my_MM' => ['Burmese', 'မြန်မာ'],
        'ne_NP' => ['Nepali', 'नेपाली'],
        'nl_BE' => ['Dutch (Belgium)', 'Nederlands (België)'],
        'nl_NL' => ['Dutch (Netherlands)', 'Nederlands (Nederland)'],
        'no_NO' => ['Norwegian', 'Norsk'],
        'or_IN' => ['Odia', 'ଓଡ଼ିଆ'],
        'pa_IN' => ['Punjabi', 'ਪੰਜਾਬੀ'],
        'pl_PL' => ['Polish', 'Polski'],
        'pt_BR' => ['Portuguese (Brazil)', 'Português (Brasil)'],
        'pt_PT' => ['Portuguese (Portugal)', 'Português (Portugal)'],
        'ro_RO' => ['Romanian', 'Română'],
        'ru_RU' => ['Russian', 'Русский'],
        'si_LK' => ['Sinhala', 'සිංහල'],
        'sk_SK' => ['Slovak', 'Slovenčina'],
        'sl_SI' => ['Slovenian', 'Slovenščina'],
        'sq_AL' => ['Albanian', 'Shqip'],
        'sr_RS' => ['Serbian', 'Српски'],
        'sv_SE' => ['Swedish', 'Svenska'],
        'sw_KE' => ['Swahili', 'Kiswahili'],
        'ta_IN' => ['Tamil', 'தமிழ்'],
        'te_IN' => ['Telugu', 'తెలుగు'],
        'th_TH' => ['Thai', 'ไทย'],
        'tr_TR' => ['Turkish', 'Türkçe'],
        'uk_UA' => ['Ukrainian', 'Українська'],
        'ur_PK' => ['Urdu', 'اردو'],
        'uz_UZ' => ['Uzbek', 'O‘zbek'],
        'vi_VN' => ['Vietnamese', 'Tiếng Việt'],
        'zh_CN' => ['Chinese (Simplified)', '中文 (简体)'],
        'zh_TW' => ['Chinese (Traditional)', '中文 (繁體)'],
    ];

    /**
     * Returns the locales (all, or the given codes) as code => name, with English or local names.
     *
     * @param array $codes
     * @param bool $useLocalizedNames
     * @return array
     */
    public static function getLocales(array $codes = [], bool $useLocalizedNames = false): array
    {
        $codes = empty($codes) ? array_keys(self::$locales) : $codes;
        $result = [];

        foreach ($codes as $code) {
            if (isset(self::$locales[$code])) {
                $result[$code] = $useLocalizedNames ? self::$locales[$code][self::LOCALIZED_INDEX] :
                    self::$locales[$code][self::ENGLISH_INDEX];
            }
        }

        return $result;
    }

    /**
     * Returns all the locale codes.
     *
     * @return array
     */
    public static function getCodes(): array
    {
        return array_keys(self::$locales);
    }

    /**
     * Returns the locale's English or local name, or false if the code is unknown.
     *
     * @param string $code
     * @param bool $useLocalizedNames
     * @return string|false
     */
    public static function getName(string $code, bool $useLocalizedNames = false): string|false
    {
        if (!isset(self::$locales[$code])) {
            return false;
        }

        return $useLocalizedNames ? self::$locales[$code][self::LOCALIZED_INDEX] :
            self::$locales[$code][self::ENGLISH_INDEX];
    }

    /**
     * Returns true if the locale code exists.
     *
     * @param string $code
     * @return bool
     */
    public static function codeExists(string $code): bool
    {
        return array_key_exists($code, self::$locales);
    }
}
