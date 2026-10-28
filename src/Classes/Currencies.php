<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

/**
 * Currency codes with their symbols and their English and local names.
 *
 * @package Enicore\Rivena
 */
class Currencies
{
    const int SYMBOL_INDEX = 0;
    const int ENGLISH_INDEX = 1;
    const int LOCALIZED_INDEX = 2;

    protected static array $currencies = [
        'AED' => ['د.إ', 'United Arab Emirates Dirham', 'درهم إماراتي'],
        'AFN' => ['؋', 'Afghan Afghani', 'افغانی'],
        'ALL' => ['L', 'Albanian Lek', 'Lek Shqiptar'],
        'AMD' => ['֏', 'Armenian Dram', 'Հայ դրամ'],
        'AOA' => ['Kz', 'Angolan Kwanza', 'Kwanza'],
        'ARS' => ['$', 'Argentine Peso', 'Peso Argentino'],
        'AUD' => ['A$', 'Australian Dollar', 'Australian Dollar'],
        'AWG' => ['ƒ', 'Aruban Florin', 'Arubaanse Florin'],
        'AZN' => ['₼', 'Azerbaijani Manat', 'Azərbaycan Manatı'],
        'BAM' => ['KM', 'Bosnia-Herzegovina Convertible Mark', 'Konvertibilna Marka'],
        'BBD' => ['Bds$', 'Barbadian Dollar', 'Barbados Dollar'],
        'BDT' => ['৳', 'Bangladeshi Taka', 'বাংলা টাকা'],
        'BHD' => ['ب.د', 'Bahraini Dinar', 'دينار بحريني'],
        'BIF' => ['FBu', 'Burundian Franc', 'Franc Burundais'],
        'BMD' => ['$', 'Bermudian Dollar', 'Bermudian Dollar'],
        'BND' => ['B$', 'Brunei Dollar', 'Ringgit Brunei'],
        'BOB' => ['Bs.', 'Bolivian Boliviano', 'Boliviano'],
        'BRL' => ['R$', 'Brazilian Real', 'Real Brasileiro'],
        'BSD' => ['B$', 'Bahamian Dollar', 'Bahamian Dollar'],
        'BTN' => ['Nu.', 'Bhutanese Ngultrum', 'ভাটানেস এনগুলটুম'],
        'BWP' => ['P', 'Botswana Pula', 'Pula'],
        'BYN' => ['Br', 'Belarusian Ruble', 'Беларускі Рубель'],
        'BZD' => ['BZ$', 'Belize Dollar', 'Belize Dollar'],
        'CAD' => ['C$', 'Canadian Dollar', 'Canadian Dollar'],
        'CDF' => ['FC', 'Congolese Franc', 'Franc Congolais'],
        'CHF' => ['CHF', 'Swiss Franc', 'Schweizer Franken'],
        'CLP' => ['$', 'Chilean Peso', 'Peso Chileno'],
        'CNY' => ['¥', 'Chinese Yuan', '人民币'],
        'COP' => ['$', 'Colombian Peso', 'Peso Colombiano'],
        'CRC' => ['₡', 'Costa Rican Colón', 'Colón Costarricense'],
        'CUP' => ['₱', 'Cuban Peso', 'Peso Cubano'],
        'CVE' => ['$', 'Cape Verdean Escudo', 'Escudo Cabo-Verdiano'],
        'CZK' => ['Kč', 'Czech Koruna', 'Česká Koruna'],
        'DJF' => ['Fdj', 'Djiboutian Franc', 'Franc Djiboutien'],
        'DKK' => ['kr', 'Danish Krone', 'Dansk Krone'],
        'DOP' => ['RD$', 'Dominican Peso', 'Peso Dominicano'],
        'DZD' => ['دج', 'Algerian Dinar', 'دينار جزائري'],
        'EGP' => ['£', 'Egyptian Pound', 'جنيه مصري'],
        'ERN' => ['Nfk', 'Eritrean Nakfa', 'ልብን ናክፋ'],
        'ETB' => ['ላ', 'Ethiopian Birr', 'ኢትዮጵኛ ቢር'],
        'EUR' => ['€', 'Euro', 'Euro'],
        'FJD' => ['$', 'Fijian Dollar', 'Fijian Dollar'],
        'FKP' => ['£', 'Falkland Islands Pound', 'Falkland Islands Pound'],
        'GBP' => ['£', 'British Pound', 'Pound Sterling'],
        'GEL' => ['₾', 'Georgian Lari', 'ლარი'],
        'GHS' => ['₵', 'Ghanaian Cedi', 'Cedi'],
        'GIP' => ['£', 'Gibraltar Pound', 'Gibraltar Pound'],
        'GMD' => ['D', 'Gambian Dalasi', 'Dalasi'],
        'GNF' => ['FG', 'Guinean Franc', 'Franc Guénéen'],
        'GTQ' => ['Q', 'Guatemalan Quetzal', 'Quetzal Guatemalteco'],
        'GYD' => ['$', 'Guyanese Dollar', 'Guyanese Dollar'],
        'HKD' => ['HK$', 'Hong Kong Dollar', '香港元'],
        'HNL' => ['L', 'Honduran Lempira', 'Lempira Hondureña'],
        'HTG' => ['G', 'Haitian Gourde', 'Gourde Haïtienne'],
        'HUF' => ['Ft', 'Hungarian Forint', 'Magyar Forint'],
        'IDR' => ['Rp', 'Indonesian Rupiah', 'Rupiah Indonesia'],
        'ILS' => ['₪', 'Israeli New Shekel', 'שקל חדש'],
        'INR' => ['₹', 'Indian Rupee', 'भारतीय रूपया'],
        'IQD' => ['ع.د', 'Iraqi Dinar', 'دينار عراقي'],
        'IRR' => ['﷼', 'Iranian Rial', 'ریال ایرانی'],
        'ISK' => ['kr', 'Icelandic Króna', 'Íslensk Króna'],
        'JMD' => ['J$', 'Jamaican Dollar', 'Jamaican Dollar'],
        'JOD' => ['د.ا', 'Jordanian Dinar', 'دينار أردني'],
        'JPY' => ['¥', 'Japanese Yen', '日本円'],
        'KES' => ['Ksh', 'Kenyan Shilling', 'Shilingi ya Kenya'],
        'KGS' => ['сом', 'Kyrgyzstani Som', 'Кыргыз Сом'],
        'KHR' => ['៛', 'Cambodian Riel', 'ឡាស៊ីករាតា'],
        'KMF' => ['CF', 'Comorian Franc', 'Franc Comorien'],
        'KPW' => ['₩', 'North Korean Won', '조선 원'],
        'KRW' => ['₩', 'South Korean Won', '한국 원'],
        'KWD' => ['د.ك', 'Kuwaiti Dinar', 'دينار كويتي'],
        'KYD' => ['CI$', 'Cayman Islands Dollar', 'Cayman Islands Dollar'],
        'KZT' => ['₸', 'Kazakhstani Tenge', 'Қазақ Тұңғе'],
        'LAK' => ['₭', 'Lao Kip', 'ກີບໄລ໊'],
        'LBP' => ['ل.ل', 'Lebanese Pound', 'جنيه لبناني'],
        'LKR' => ['₨', 'Sri Lankan Rupee', 'ස්‍රී ලුපියු'],
        'LRD' => ['$', 'Liberian Dollar', 'Liberian Dollar'],
        'LSL' => ['L', 'Lesotho Loti', 'Lesotho Loti'],
        'LYD' => ['ل.د', 'Libyan Dinar', 'دينار ليبي'],
        'MAD' => ['د.م.', 'Moroccan Dirham', 'درهم مغربي'],
        'MDL' => ['L', 'Moldovan Leu', 'Leu Moldovenesc'],
        'MGA' => ['Ar', 'Malagasy Ariary', 'Ariary Malagasy'],
        'MKD' => ['ден', 'Macedonian Denar', 'Македонски денар'],
        'MMK' => ['Ks', 'Myanmar Kyat', 'မတ်တို'],
        'MNT' => ['₮', 'Mongolian Tögrög', 'Монгол Төгөрөг'],
        'MOP' => ['P', 'Macanese Pataca', '澳門元'],
        'MRU' => ['أ.م', 'Mauritanian Ouguiya', 'أوقية موريتانية'],
        'MUR' => ['₨', 'Mauritian Rupee', 'Roupie Mauricienne'],
        'MVR' => ['Rf', 'Maldivian Rufiyaa', 'ރފކއެ ދިގ'],
        'MWK' => ['MK', 'Malawian Kwacha', 'Kwacha'],
        'MXN' => ['$', 'Mexican Peso', 'Peso Mexicano'],
        'MYR' => ['RM', 'Malaysian Ringgit', 'Ringgit Malaysia'],
        'MZN' => ['MT', 'Mozambican Metical', 'Metical Moçambicano'],
        'NAD' => ['$', 'Namibian Dollar', 'Namibian Dollar'],
        'NGN' => ['₦', 'Nigerian Naira', 'Naira'],
        'NIO' => ['C$', 'Nicaraguan Córdoba', 'Córdoba Nicaragüense'],
        'NOK' => ['kr', 'Norwegian Krone', 'Norsk Krone'],
        'NPR' => ['₨', 'Nepalese Rupee', 'नेपाली रूपें'],
        'NZD' => ['$', 'New Zealand Dollar', 'New Zealand Dollar'],
        'OMR' => ['ر.ع', 'Omani Rial', 'ريال عماني'],
        'PAB' => ['B/.', 'Panamanian Balboa', 'Balboa Panameño'],
        'PEN' => ['S/.', 'Peruvian Sol', 'Sol Peruano'],
        'PGK' => ['K', 'Papua New Guinean Kina', 'Kina'],
        'PHP' => ['₱', 'Philippine Peso', 'Piso ng Pilipinas'],
        'PKR' => ['₨', 'Pakistani Rupee', 'پاکستانی روپیہ'],
        'PLN' => ['zł', 'Polish Zloty', 'Złoty Polski'],
        'PYG' => ['₲', 'Paraguayan Guarani', 'Guaraní Paraguayo'],
        'QAR' => ['ر.ق', 'Qatari Rial', 'ريال قطري'],
        'RON' => ['L', 'Romanian Leu', 'Leu Românesc'],
        'RSD' => ['дин.', 'Serbian Dinar', 'Српски динар'],
        'RUB' => ['₽', 'Russian Ruble', 'Российский рубль'],
        'RWF' => ['FRw', 'Rwandan Franc', 'Franc Rwandais'],
        'SAR' => ['﷼', 'Saudi Riyal', 'ريال سعودي'],
        'SBD' => ['SI$', 'Solomon Islands Dollar', 'Solomon Islands Dollar'],
        'SCR' => ['₨', 'Seychellois Rupee', 'Roupie Seychelloise'],
        'SDG' => ['ج.س.', 'Sudanese Pound', 'جنيه سوداني'],
        'SEK' => ['kr', 'Swedish Krona', 'Svensk Krona'],
        'SGD' => ['S$', 'Singapore Dollar', 'Singapore Dollar'],
        'SHP' => ['£', 'Saint Helena Pound', 'Saint Helena Pound'],
        'SLE' => ['Le', 'Sierra Leonean Leone', 'Sierra Leonean Leone'],
        'SOS' => ['Sh', 'Somali Shilling', 'Shilin Soomaali'],
        'SRD' => ['$', 'Surinamese Dollar', 'Surinaamse Dollar'],
        'SSP' => ['£', 'South Sudanese Pound', 'South Sudanese Pound'],
        'STN' => ['Db', 'São Tomé and Príncipe Dobra', 'Dobra São-tomense'],
        'SYP' => ['£', 'Syrian Pound', 'الليرة السورية'],
        'SZL' => ['L', 'Swazi Lilangeni', 'Lilangeni'],
        'THB' => ['฿', 'Thai Baht', 'บาทไทย'],
        'TJS' => ['ЅМ', 'Tajikistani Somoni', 'Сомони'],
        'TMT' => ['m', 'Turkmenistani Manat', 'Türkmen Manaty'],
        'TND' => ['د.ت', 'Tunisian Dinar', 'دينار تونسي'],
        'TOP' => ['T$', 'Tongan Paʻanga', 'Tongan Paʻanga'],
        'TRY' => ['₺', 'Turkish Lira', 'Türk Lirası'],
        'TTD' => ['TT$', 'Trinidad and Tobago Dollar', 'Trinidad and Tobago Dollar'],
        'TWD' => ['NT$', 'New Taiwan Dollar', '新台幣'],
        'TZS' => ['Sh', 'Tanzanian Shilling', 'Shilingi ya Kitanzania'],
        'UAH' => ['₴', 'Ukrainian Hryvnia', 'Українська гривня'],
        'UGX' => ['USh', 'Ugandan Shilling', 'Shilingi ya Uganda'],
        'USD' => ['$', 'US Dollar', 'US Dollar'],
        'UYU' => ['$', 'Uruguayan Peso', 'Peso Uruguayo'],
        'UZS' => ['сўм', 'Uzbekistani Soʻm', 'Ўзбекистон сўм'],
        'VES' => ['Bs.', 'Venezuelan Bolívar', 'Bolívar Venezolano'],
        'VND' => ['₫', 'Vietnamese Dong', 'Đồng Việt Nam'],
        'VUV' => ['VT', 'Vanuatu Vatu', 'Vanuatu Vatu'],
        'WST' => ['T', 'Samoan Tala', 'Samoan Tala'],
        'XAF' => ['FCFA', 'Central African CFA Franc', 'Franc CFA'],
        'XCD' => ['EC$', 'East Caribbean Dollar', 'East Caribbean Dollar'],
        'XCG' => ['Cg', 'Caribbean Guilder', 'Caribische Gulden'],
        'XOF' => ['CFA', 'West African CFA Franc', 'Franc CFA'],
        'XPF' => ['CFP', 'CFP Franc', 'Franc CFP'],
        'YER' => ['﷼', 'Yemeni Rial', 'ريال يمني'],
        'ZAR' => ['R', 'South African Rand', 'Suid-Afrikaanse Rand'],
        'ZMW' => ['ZK', 'Zambian Kwacha', 'Zambian Kwacha'],
        'ZWG' => ['ZiG', 'Zimbabwe Gold', 'Zimbabwe Gold'],
    ];

    /**
     * Returns the currencies (all, or the given codes) as code => [symbol, name], with English or local names.
     *
     * @param array $codes
     * @param bool $useLocalizedNames
     * @return array
     */
    public static function getCurrencies(array $codes = [], bool $useLocalizedNames = false): array
    {
        $codes = empty($codes) ? array_keys(self::$currencies) : array_map('strtoupper', $codes);
        $result = [];

        foreach ($codes as $code) {
            if (isset(self::$currencies[$code])) {
                $result[$code] = [
                    self::$currencies[$code][self::SYMBOL_INDEX],
                    $useLocalizedNames ? self::$currencies[$code][self::LOCALIZED_INDEX] :
                        self::$currencies[$code][self::ENGLISH_INDEX]
                ];
            }
        }

        return $result;
    }

    /**
     * Returns all the currency codes.
     *
     * @return array
     */
    public static function getCodes(): array
    {
        return array_keys(self::$currencies);
    }

    /**
     * Returns the currency's symbol, or false if the code is unknown.
     *
     * @param string $code
     * @return string|false
     */
    public static function getSymbol(string $code): string|false
    {
        return self::$currencies[strtoupper($code)][self::SYMBOL_INDEX] ?? false;
    }

    /**
     * Returns the currency's English or local name, or false if the code is unknown.
     *
     * @param string $code
     * @param bool $useLocalizedNames
     * @return string|false
     */
    public static function getName(string $code, bool $useLocalizedNames = false): string|false
    {
        $code = strtoupper($code);

        if (!isset(self::$currencies[$code])) {
            return false;
        }

        return $useLocalizedNames ? self::$currencies[$code][self::LOCALIZED_INDEX] :
            self::$currencies[$code][self::ENGLISH_INDEX];
    }

    /**
     * Returns true if the currency code exists.
     *
     * @param string $code
     * @return bool
     */
    public static function codeExists(string $code): bool
    {
        return isset(self::$currencies[strtoupper($code)]);
    }

}