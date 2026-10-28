<?php
/**
 * Enicore Rivena.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Random\RandomException;

/**
 * Random test data: words, names, emails, URLs, HTML...
 *
 * @package Enicore\Rivena
 */
class Random
{
    /**
     * Returns a random IPv4 address.
     *
     * @return string
     */
    public static function ip(): string
    {
        return rand(1, 255) . "." . rand(1, 255) . "." . rand(1, 255) . "." . rand(1, 255);
    }

    /**
     * Returns a random UUID (v4).
     *
     * @return string
     * @throws RandomException
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Returns a random domain extension, e.g. "com".
     *
     * @return string
     */
    public static function tld(): string
    {
        $list = [
            "com", "org", "net", "edu", "gov", "mil", "int", "eu", "it", "de", "co.uk", "cn", "jp", "sg", "us", "info",
            "biz", "co", "me", "tv", "cc", "io", "ai", "uk", "ca", "au", "fr", "es", "nl", "in", "ch", "se", "no", "za",
            "pl", "gr", "br", "ar", "mx", "kr", "hk", "vn", "ph", "my", "id", "tr", "sa", "ae", "nz", "ie", "th", "be",
            "fi", "dk", "pt", "cz", "ro", "sk", "hu", "bg", "si", "lt", "lv", "ee", "is", "lu", "mt", "cy", "hr", "ba",
            "rs", "by", "kz", "ge", "md",
        ];

        return $list[rand(0, count($list) - 1)];
    }

    /**
     * Returns a random domain name.
     *
     * @return string
     */
    public static function domain(): string
    {
        return self::word() . "." . self::tld();
    }

    /**
     * Returns a random URL, with a random path by default.
     *
     * @param bool $includePath
     * @return string
     */
    public static function url(bool $includePath = true): string
    {
        $path = "";

        if ($includePath) {
            for ($i = 0, $ni = rand(1, 4); $i < $ni; $i++) {
                $path .= "/" . self::word();
            }
        }

        return "https://" . self::domain() . $path;
    }

    /**
     * Returns a random string of digits, between the lengths.
     *
     * @param int $minLength
     * @param int $maxLength
     * @param bool $includeZero
     * @return string
     */
    public static function number(int $minLength = 5, int $maxLength = 5, bool $includeZero = false): string
    {
        $minLength = max(1, $minLength);
        $maxLength = max($minLength, $maxLength);
        $characters = ($includeZero ? "0" : "") . "123456789";
        $result = "";

        for ($i = 0, $ni = rand($minLength, $maxLength); $i < $ni; $i++) {
            $result .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $result;
    }

    /**
     * Returns a random string of letters, between the lengths, with spaces by default.
     *
     * @param int $minLength
     * @param int $maxLength
     * @param bool $useSpaces
     * @param bool $upperCase
     * @return string
     */
    public static function string(int $minLength = 10, int $maxLength = 40, bool $useSpaces = true,
                                  bool $upperCase = false): string
    {
        $minLength = max(1, $minLength);
        $maxLength = max($minLength, $maxLength);
        $letters = $upperCase ? "QWERTYUIOPASDFGHJKLZXCVBNM" : "qwertyuiopasdfghjklzxcvbnm";
        $characters = $useSpaces ? $letters . "   " : $letters;
        $length = rand($minLength, $maxLength);
        $result = "";

        // no spaces at the ends, so the string has the requested length
        for ($i = 0; $i < $length; $i++) {
            $chars = $i == 0 || $i == $length - 1 ? $letters : $characters;
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $result;
    }

    /**
     * Returns a random color name, e.g. "Blue".
     *
     * @return string
     */
    public static function color(): string
    {
        $list = ["Blue", "Red", "Green", "Black", "White", "Yellow", "Pink", "Orange", "Silver", "Gray"];

        return $list[array_rand($list)];
    }

    /**
     * Returns a random name of capitalized words.
     *
     * @param int $minWordCount
     * @param int $maxWordCount
     * @return string
     */
    public static function name(int $minWordCount = 2, int $maxWordCount = 2): string
    {
        $minWordCount = max(1, $minWordCount);
        $maxWordCount = max($minWordCount, $maxWordCount);
        $result = "";

        for ($i = 0, $ni = rand($minWordCount, $maxWordCount); $i < $ni; $i++) {
            $result .= self::word(true) . " ";
        }

        return trim($result);
    }

    /**
     * Returns a random phone number, e.g. "(123) 456-7890".
     *
     * @param int $firstPartLength
     * @param int $secondPartLength
     * @param bool $includeDialingCode
     * @return string
     */
    public static function phoneNumber(int $firstPartLength = 3, int $secondPartLength = 4,
                                       bool $includeDialingCode = true): string
    {
        $firstPartLength = max(1, $firstPartLength);
        $secondPartLength = max(1, $secondPartLength);
        $result = $includeDialingCode ? "(" . self::number(3, 3, true) . ") " : "";
        $result .= self::number($firstPartLength, $firstPartLength, true);

        return $result . "-" . self::number($secondPartLength, $secondPartLength, true);
    }

    /**
     * Returns a random email address.
     *
     * @return string
     */
    public static function email(): string
    {
        return self::word() . (rand(0, 1) ? "." . self::word() : "") . "@" . self::word() . "." . self::tld();
    }

    /**
     * Returns a random country code.
     *
     * @return string
     */
    public static function countryCode(): string
    {
        $list = Countries::getCodes();

        return $list[rand(0, count($list) - 1)];
    }

    /**
     * Returns random words joined with the delimiter.
     *
     * @param int $minWordCount
     * @param int $maxWordCount
     * @param string $delimiter
     * @param bool $firstCapitalLetter
     * @return string
     */
    public static function wordList(int  $minWordCount = 5, int $maxWordCount = 5, string $delimiter = ", ",
                                    bool $firstCapitalLetter = true): string
    {
        $minWordCount = max(1, $minWordCount);
        $maxWordCount = max($minWordCount, $maxWordCount);

        return implode($delimiter, array_map(fn() => self::word($firstCapitalLetter),
            range(1, rand($minWordCount, $maxWordCount))));
    }

    /**
     * Returns random HTML: paragraphs, headings, lists, tables and an image.
     *
     * @param int $minParagraphs
     * @param int $maxParagraphs
     * @return string
     */
    public static function html(int $minParagraphs = 3, int $maxParagraphs = 5): string
    {
        $minParagraphs = max(1, $minParagraphs);
        $maxParagraphs = max($minParagraphs, $maxParagraphs);
        $paragraphs = rand($minParagraphs, $maxParagraphs);
        $hasImage = false;
        $output = "";
        $i = 1;

        while ($i <= $paragraphs) {
            if (!rand(0, 5)) {
                $output .= "<h4>" . self::wordList(4, 6, " ") . "</h4>";
                $i++;
            }

            $output .= "<p style='padding:3px 0;'>" . self::htmlParagraph(5, 10) . "</p>";
            $i++;

            if (!rand(0, 4)) {
                $output .= self::htmlList();
                $i++;
            }

            if (!rand(0, 4)) {
                $output .= self::htmlTable();
                $i++;
            }

            if (($i > $paragraphs && !$hasImage) || !rand(0, 5)) {
                $output .=
                    "<p style='padding:3px 0;text-align:center;'>".
                    "<img src='https://picsum.photos/300/200' alt='' />".
                    "</p>".
                    "<p style='padding:3px 0;'>" . self::htmlParagraph(5, 10) . "</p>";
                $i++;
                $hasImage = true;
            }
        }

        return "<html lang='en'><body>".
            "<p style='padding:3px 0;'>" . self::word(true) . " " . self::word(true) . ",</p>".
            $output.
            "<p style='padding:3px 0;'>" . self::word(true) . ",<br />" . self::word(true) . "</p>".
            "</body></html>";
    }

    /**
     * Returns a random paragraph, with some bold, italic, underlined and colored words.
     *
     * @param int $minSentenceCount
     * @param int $maxSentenceCount
     * @return string
     */
    public static function htmlParagraph(int $minSentenceCount = 1, int $maxSentenceCount = 3): string
    {
        $minSentenceCount = max(1, $minSentenceCount);
        $maxSentenceCount = max($minSentenceCount, $maxSentenceCount);
        $colors = ["#AF265F", "#278911", "#AA4225"];
        $tags = [
            ["<span style='color:%s'>", "</span>"],
            ["<strong>", "</strong>"],
            ["<em>", "</em>"],
            ["<u>", "</u>"],
        ];
        $result = "";

        for ($i = 0, $ni = rand($minSentenceCount, $maxSentenceCount); $i < $ni; $i++) {
            for ($j = 0, $nj = rand(5, 20); $j < $nj; $j++) {
                if (rand(0, 10) == 10) {
                    $c = rand(1, 10);
                    $tag = $tags[rand(0, count($tags) - 1)];
                    $word = "";

                    for ($k = 1; $k <= $c; $k++) {
                        $word .= self::word($j == 0) . " ";
                    }

                    $word = (strpos($tag[0], "color") ? sprintf($tag[0], $colors[rand(0, count($colors) - 1)]) : $tag[0]) .
                        $word . $tag[1];

                } else {
                    $word = self::word($j == 0);
                }

                $result .= $word . " ";
            }
            $result = substr($result, 0, -1) . ". ";
        }

        return trim($result);
    }

    /**
     * Returns a random <ul> list.
     *
     * @param int $minItems
     * @param int $maxItems
     * @return string
     */
    public static function htmlList(int $minItems = 5, int $maxItems = 10): string
    {
        $minItems = max(1, $minItems);
        $maxItems = max($minItems, $maxItems);
        $result = "";

        for ($i = 0, $ni = rand($minItems, $maxItems); $i < $ni; $i++) {
            $line = "";

            for ($j = 0, $nj = rand(3, 6); $j < $nj; $j++) {
                $line .= self::word($j == 0) . " ";
            }

            $result .= "<li>" . trim($line) . "</li>";
        }

        return "<ul>$result</ul>";
    }

    /**
     * Returns a random <table>.
     *
     * @param int $minRows
     * @param int $maxRows
     * @return string
     */
    public static function htmlTable(int $minRows = 3, int $maxRows = 6): string
    {
        $backgrounds = ["#601B08", "#023D77", "#1B5E05", "#4E0A96"];
        $bg = $backgrounds[rand(0, count($backgrounds) - 1)];
        $cols = rand(3, 6);
        $result = "";

        for ($i = 0; $i < $cols; $i++) {
            $result .= "<th style='padding:5px;border:1px solid $bg;background:$bg;color:#fff;'>" . self::word(true) .
                "</th>";
        }

        $result = "<tr>$result</tr>";

        for ($i = 0, $ni = rand($minRows, $maxRows); $i < $ni; $i++) {
            $result .= "<tr>";

            for ($j = 0; $j < $cols; $j++) {
                $line = "";

                for ($k = 0, $nk = rand(2, 5); $k < $nk; $k++) {
                    $line .= self::word($k == 0) . " ";
                }
                $result .= "<td style='padding:5px;border:1px solid $bg;'>" . trim($line) . "</td>";
            }

            $result .= "</tr>";
        }

        return "<table style='width:95%;border-collapse:collapse;margin:5px auto;'>$result</table>";
    }

    /**
     * Returns a random browser's user agent.
     *
     * @return string
     */
    public static function userAgent(): string
    {
        $userAgents = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Edg/128.0.0.0",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Linux; Android 9; SM-S906N Build/PQ3A.190605.05081124; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/91.0.4472.114 Mobile Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36 Edg/127.0.0.0",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 OPR/112.0.0.0",
            "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/92.0.4515.105 Mobile Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36",
            "Mozilla/5.0 (X11; Linux x86_64; rv:129.0) Gecko/20100101 Firefox/129.0",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Linux; Android 8.1.0; K) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/127.0.6533.103 Mobile Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Linux; Android 7.0; SM-G892A Build/NRD90M; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/60.0.3112.107 Mobile Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36",
            "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:130.0) Gecko/20100101 Firefox/130.0",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Linux; Android 11; K) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/112.0.0.0 Mobile Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_16) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Linux; Android 12; SM-A325F) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/111.0.0.0 Mobile Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Linux; Android 10; SM-G965F Build/QP1A.190711.020) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/95.0.4638.80 Mobile Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36"
        ];

        return $userAgents[array_rand($userAgents)];
    }

    /**
     * Returns a random word.
     *
     * @param bool $firstLetterCapital
     * @return string
     */
    public static function word(bool $firstLetterCapital = false): string
    {
        $words = [
            "semangat", "perhatian", "kegairahan", "kegiatan", "zealot", "orang", "yg", "bersemangat", "dlm", "suatu",
            "usaha", "fanatik", "peng", "zealous", "rajin", "bersemangat", "zenith", "puncak", "ketenaran", "zephyr",
            "angin", "barat", "angin", "sepoi", "sepoi", "zest", "minat", "besar", "gairah", "animo", "zest", "bumbu",
            "rasa", "enak", "perangsang", "zip", "bunyi", "desing", "semangat", "zipper", "retsluiting", "zip", "fastener",
            "retsluiting", "yatch", "kapal", "ringan", "utk", "bunga", "kapal", "mewah", "utk", "pesiar", "yak", "lembu",
            "berbulu", "panjang", "di", "asia", "tengah", "yam", "ubi", "yap", "mendengking", "menyalak", "omong", "kosong",
            "yard", "kayu", "utk", "menggantungkan", "yearning", "rindu", "sekali", "yeast", "ragi", "yellow", "fever",
            "sakit", "kuning", "yelp", "menyalak", "yeoman", "menolong", "org", "dlm", "kesusahan", "yew", "pohon",
            "cemara", "berdaun", "hijau", "muda", "yield", "menghasilkan", "kayu", "tengkuk", "pikulan", "bag", "dada",
            "pd", "gaun", "yoke", "of", "oxen", "sepasang", "lembu", "yoke", "pass", "udik", "yolk", "kuning", "telur",
            "yon", "di", "sana", "yonder", "di", "sana", "yore", "berkenaan", "dgn", "jaman", "hari", "natal", "wroth",
            "marah", "gusar", "wrought", "iron", "besi", "tempa", "wroughtup", "state", "kead", "gelisah", "remedies",
            "obat", "yg", "dijual", "oleh", "tukang", "obat", "quadrangle", "benda", "berbentuk", "segi", "bj", "alat",
            "pengukur", "sudut", "quadrilateral", "benda", "berbentuk", "segi", "quadruped", "hewan", "berkaki", "quagmire",
            "rawa", "paya", "quail", "takut", "hilang", "semangat", "quaint", "menarik", "menyenangkan", "krn", "gereja",
            "kristen", "yg", "mengadakan", "kebaktian", "tan", "quaker", "pa", "seorang", "pendeta", "qualification",
            "qualm", "ragu", "ragu", "sangsi", "sesal", "mual", "quandary", "kead", "ragu", "ragu", "bimbang", "dilema",
            "quarrel", "buruan", "perburuan", "mangsa", "quart", "galon", "ltr", "quartette", "penyanyi", "quartz", "batu",
            "akik", "permata", "sajak", "baris", "quaver", "bunyi", "bergetar", "gemetar", "titi", "nada", "quay",
            "dermaga", "queasy", "memualkan", "quarter", "penjuru", "daerah", "kota", "ampun", "pon", "pondokan", "markas",
            "quarterday", "hari", "pembayaran", "api", "harapan", "menghilangkan", "haus", "querulous", "suka", "mengeluh",
            "mengomel", "query", "menanyakan", "berselisih", "ttg", "hal", "yg", "kecil", "quicklime", "kapur", "mentah",
            "quicksand", "pasir", "hanyut", "diam", "masif", "quietude", "kesunyian", "ketenangan", "quieten",
            "menenangkan", "quill", "bulu", "burung", "pen", "quinine", "pil", "kina", "quintessence", "contoh", "yg",
            "sempurna", "quintette", "penyanyi", "org", "quip", "menggetarkan", "tempat", "anak", "panah", "qui", "vive",
            "waspada", "berjaga", "jaga", "quixotic", "pemurah", "suatu", "permainan", "quota", "jatah", "quotient",
            "hasil", "bagi", "quoth", "berkata", "quote", "mengutip", "ternak", "kowtow", "membungkuk", "sangat",
            "menghormati", "kosher", "toko", "makanan", "bangsa", "yahudi", "kecil", "knick", "knack", "barang", "penghias",
            "kecil", "yg", "tdk", "penting", "knickers", "celana", "dalam", "lutut", "knead", "memukul", "adonan",
            "meremas", "memijit", "mengurut", "knapsack", "ransel", "knave", "penjahat", "ketrampilan", "ketangkasan",
            "seni", "kith", "and", "kin", "teman", "sanak", "saudara", "kite", "burung", "elang", "alat", "perkakas",
            "kipper", "ikan", "kecil", "yg", "diasap", "diasin", "knob", "tombol", "menonjol", "bungkal", "elang", "kecil",
            "keg", "tong", "kecil", "kennel", "kandang", "anjing", "kerb", "tepi", "jalan", "trotoar", "kerchief", "kead",
            "canggung", "serba", "salah", "kettledrum", "gendang", "kidney", "ginjal", "kidnap", "menculik", "kid", "anak",
            "pembakaran", "kapur", "batubara", "kin", "next", "of", "saudara", "dekat", "kine", "sapi", "lembu", "kiosk",
            "kios", "kinswoman", "kerabat", "laki", "perempuan", "kingfisher", "burung", "kecil", "pemakan", "ikan", "di",
            "sungai", "menyalakan", "api", "misalnya", "ranting", "kering", "kangaroo", "kangguru", "kapok", "kapuk",
            "keel", "lunas", "luar", "jute", "goni", "juvenile", "remaja", "juxtapose", "menempatkan", "berdampingan",
            "juxtaposition", "posisi", "jurisprudence", "ilmu", "ttg", "hukum", "jurisdiction", "hal", "mengadili",
            "daerah", "kekuasaan", "kekuasaan", "pohon", "cemara", "yg", "buahnya", "utk", "minyak", "juncture", "hubungan",
            "junction", "at", "this", "kead", "spt", "hakim", "kehakiman", "judicial", "berkenaan", "dgn", "pengadilan",
            "judicious", "bijaksana", "jumble", "bercampur", "junketing", "pesta", "makan", "juggernaut", "keyakinan",
            "mengorbankan", "diri", "sendiri", "jugular", "veins", "menunjukkan", "kemenangan", "kegembiraan", "jubilation",
            "sorak", "kegirangan", "jubilee", "ultah", "ke", "silver", "penuh", "kegembiraan", "jowl", "rahang", "bag",
            "bawah", "wajah", "cheek", "by", "jowl", "sangat", "rapat", "cheek", "berkelahi", "dgn", "tombak", "sambil",
            "naik", "kuda", "jot", "down", "mencatat", "dgn", "cepat", "jot", "jml", "yg", "jostle", "mendesak",
            "mendorong", "jolt", "berguncang", "mengguncang", "jolly", "gembira", "jollity", "kead", "joinery", "pekerjaan",
            "tukang", "kayu", "jocund", "gembira", "jocose", "bergurau", "lucu", "jocular", "lucu", "jag", "ujung",
            "karang", "yg", "tajam", "jade", "batu", "permata", "hijau", "jackal", "serigala", "jacket", "kulit", "pesuruh",
            "jangle", "bunyi", "berdencing", "jargon", "bhs", "yg", "dipakai", "oleh", "kelompok", "tertentu", "cerah",
            "yg", "ribut", "bunyinya", "jeer", "mencemooh", "mengejek", "menertawakan", "jemmy", "linggis", "jelly",
            "ketat", "dr", "kulit", "jersey", "baju", "kaos", "jetty", "dermaga", "pangkalan", "jib", "layar", "kecil",
            "itinerary", "rencana", "perjalanan", "iou", "surat", "hutang", "isthmus", "genting", "tanah", "itch", "gatal",
            "ire", "kemarahan", "irascible", "cepat", "marah", "iris", "selaput", "pelangi", "lumba", "yg", "daunnya",
            "panjang", "inveigh", "against", "menyerang", "dgn", "kata", "inveigle", "mmebujuk", "spy", "berbuat",
            "intrude", "masuk", "tanpa", "diundang", "mengganggu", "intrusion", "gangguan", "masuk", "tanpa", "diundang",
            "inundate", "membanjiri", "intimidate", "menakuti", "mengancam", "menggertak", "intestine", "usus", "interplay",
            "surat", "wasiat", "wkt", "meninggal", "interchange", "tukar", "menukar", "intercept", "menghadang", "mencegat",
            "jujur", "lurus", "kead", "lengkap", "komplit", "intend", "bermaksud", "bertujuan", "berniat", "inter",
            "mengubur", "dr", "index", "indigenous", "pribumi", "indigo", "biru", "tua", "nila", "infantry", "pasukan",
            "jalan", "kaki", "burung", "bangkai", "burung", "nasar", "vulnerable", "mudah", "rusak", "tdk", "kebal",
            "vulgar", "tdkk", "sopan", "tdk", "berlaku", "voile", "kain", "voal", "tipis", "utk", "gaun", "viva", "voce",
            "scr", "lisan", "viz", "namely", "wadi", "sungai", "kering", "di", "mesir", "wadi", "sultry", "pengap", "panas",
            "tdk", "berangin", "superb", "hebat", "pantai", "supine", "terlentang", "malas", "pap", "bubur", "bayi",
            "papacy", "pemerintahan", "paus", "papal", "sahabat", "pallor", "muka", "pucat", "pallid", "pucat", "kelihatan",
            "sakit", "palisade", "pagar", "dr", "batang", "abandon", "putus", "asa", "batal", "abase", "menghina",
            "menurunkan", "martabat", "abasement", "penghinaan", "penjagalan", "hewan", "abbess", "kepala", "asrama",
            "biarawati", "abbott", "kepala", "biarawan", "biara", "abbey", "isinya", "abduct", "penculikan", "abet", "aid",
            "membantu", "menghasut", "abeyance", "dlm", "kead", "non", "aktif", "abound", "berlimpah", "limpah", "abide",
            "rumah", "tinggal", "abode", "rumah", "tinggal", "aboard", "dlm", "umum", "akan", "melepaskan", "jabatan",
            "abscess", "bisul", "bettle", "kumbang", "befall", "menimpa", "befit", "halus", "amfibi", "becalmed", "kapal",
            "yg", "terhenti", "krn", "tdk", "ada", "angin", "beckon", "memanggil", "bertingkah", "aneh", "bedlam", "huru",
            "hara", "rumah", "sakit", "jiwa", "bee", "lebah", "make", "a", "line", "for", "lembu", "beige", "warna", "batu",
            "pasir", "belabour", "menghantam", "belated", "terlambat", "beleaguer", "menara", "lonceng", "belie", "memberi",
            "kesan", "keliru", "tdk", "menepati", "janji", "bellicose", "suka", "berteriak", "keras", "bellows", "alat",
            "penghembus", "udara", "ke", "api", "orgel", "belly", "isi", "perut", "igneous", "bukit", "yg", "terbentuk",
            "oleh", "panas", "gunung", "berapi", "ignite", "menyalakan", "ignoble", "menancapkan", "imbecile", "dungu",
            "imbibe", "minum", "meneguk", "mencamkan", "menyerap", "imbue", "penuh", "immemorial", "dulu", "kala", "shg",
            "terlupakan", "immerse", "membenamkan", "imp", "anak", "setan", "nakal", "meneruskan", "rahasia", "impasse",
            "jln", "buntu", "impassioned", "penuh", "semangat", "odour", "berbau", "ode", "achre", "warna", "coklat",
            "kekuningan", "octagon", "al", "segi", "occident", "negara", "barat", "obviate", "welter", "berkubang",
            "kekacauan", "wend", "pergi", "ke", "wench", "perempuan", "muda", "wer", "biri", "jantan", "susu", "dibuat",
            "keju", "vale", "lembah", "upbraid", "memarahi", "upbringing", "mengasuh", "upstream", "ke", "hantu", "spool",
            "kumparan", "spoor", "jejak", "binatang", "spore", "spora", "spouse", "suami", "istri", "sprat", "tinggi",
            "runcing", "pada", "gereja", "prosody", "pengetahuan", "ttg", "persajakan", "prosaic", "biasa", "perkamusan",
            "lichen", "semacam", "lumut", "liar", "pembohong", "libation", "persembahan", "anggur", "kpd", "hewan", "laut",
            "sesuatu", "yg", "besar", "lever", "pengungkit", "leverage", "daya", "pengungkit", "levity", "batu", "kawi",
            "mange", "kurap", "mangy", "berkudis", "manger", "palungan", "mangle", "alat", "pemeras", "cucian", "manila",
            "rami", "manifest", "nyata", "daftar", "muatan", "kapal", "wujud", "manifesto", "pernyataan", "prinsip",
            "tanah", "milik", "bangsawan", "manse", "rumah", "pendeta", "grj", "presbitaria", "mansion", "rumah", "besar",
            "indah", "manure", "pupuk", "baja", "manuscipt", "naskah", "map", "out", "merancang", "menyusun", "mengatur",
            "maple", "kayu", "berlalunya", "peristiwa", "marchioness", "istri", "janda", "bangsawan", "inggris", "mare",
            "kuda", "betina", "mares", "dgn", "laut", "mariner", "pelaut", "marionette", "boneka", "yg", "digerakkan",
            "dgn", "tali", "kecil", "marital", "merah", "hati", "ayam", "maroon", "kembang", "api", "utk", "isyarat",
            "maroon", "meninggalkan", "org", "di", "pulau", "gelar", "bangsawan", "di", "bawah", "pangeran", "marrow",
            "sumsum", "vegetable", "labu", "marsh", "rawa", "marshal", "martin", "burung", "layang", "martinet", "org",
            "yg", "berpegang", "teguh", "pd", "tata", "tertib", "martyrdom", "pijit", "mast", "tiang", "masticate",
            "mengunyah", "mat", "keset", "tikar", "kusam", "matted", "rambut", "kusut", "matriarch", "wanita", "yg", "mjd",
            "kepala", "keluarga", "suku", "matricide", "pembunuhan", "ibu", "kandung",
        ];

        $word = $words[array_rand($words)];

        return $firstLetterCapital ? strtoupper($word[0]) . substr($word, 1) : $word;
    }
}
