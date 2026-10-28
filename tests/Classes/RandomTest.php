<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Rivena\Classes\Random;

#[CoversClass(\Enicore\Rivena\Classes\Random::class)]
class RandomTest extends TestCase
{
    public function testIp(): void
    {
        $ip = Random::ip();
        $this->assertMatchesRegularExpression('/^(\d{1,3}\.){3}\d{1,3}$/', $ip);

        foreach (explode('.', $ip) as $segment) {
            $this->assertGreaterThanOrEqual(1, (int)$segment);
            $this->assertLessThanOrEqual(255, (int)$segment);
        }
    }

    public function testUuid(): void
    {
        $uuid = Random::uuid();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testTld(): void
    {
        $tld = Random::tld();
        $this->assertIsString($tld);
        $this->assertNotEmpty($tld);
    }

    public function testDomain(): void
    {
        $domain = Random::domain();
        $this->assertMatchesRegularExpression('/^[a-z]+(\.[a-z]+)*\.[a-z]{2,}$/', $domain);
    }

    public function testUrl(): void
    {
        $urlWithPath = Random::url();
        $this->assertMatchesRegularExpression('/^https:\/\/[a-z]+(\.[a-z]+)*\.[a-z]{2,}(\/[a-z]+)*$/', $urlWithPath);

        $urlWithoutPath = Random::url(false);
        $this->assertMatchesRegularExpression('/^https:\/\/[a-z]+(\.[a-z]+)*\.[a-z]{2,}$/', $urlWithoutPath);
    }

    public function testNumber(): void
    {
        $number = Random::number(5, 10);
        $this->assertIsString($number);
        $this->assertGreaterThanOrEqual(5, strlen($number));
        $this->assertLessThanOrEqual(10, strlen($number));

        // the longest length too, 1 in 10 (rand() in the loop's condition made it 1 in 2800)
        $lengths = array_map(fn() => strlen(Random::number(1, 10)), range(1, 200));
        $this->assertContains(10, $lengths);
    }

    public function testString(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $string = Random::string(5, 10, true, true);
            $this->assertMatchesRegularExpression('/^[A-Z][A-Z ]{3,8}[A-Z]$/', $string);
        }

        $this->assertMatchesRegularExpression('/^[a-z]{3}$/', Random::string(3, 3, false));
        $this->assertSame(1, strlen(Random::string(0, 0)));
    }

    public function testColor(): void
    {
        $color = Random::color();
        $this->assertIsString($color);
        $this->assertNotEmpty($color);
    }

    public function testName(): void
    {
        $name = Random::name(2, 3);
        $this->assertMatchesRegularExpression('/^([A-Z][a-z]+ ){1,2}[A-Z][a-z]+$/', $name);
    }

    public function testPhoneNumber(): void
    {
        $phoneNumber = Random::phoneNumber();
        $this->assertMatchesRegularExpression('/^\(\d{3}\) \d{3}-\d{4}$/', $phoneNumber);
    }

    public function testEmail(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $email = Random::email();
            $this->assertMatchesRegularExpression('/^[a-z]+(\.[a-z]+)?@[a-z]+\.[a-z]{2,}(\.[a-z]{2})?$/', $email);
        }
    }

    public function testCountryCode(): void
    {
        $countryCode = Random::countryCode();
        $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $countryCode);
    }

    public function testWord(): void
    {
        $word = Random::word();
        $this->assertIsString($word);
        $this->assertNotEmpty($word);
    }

    public function testWordList(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $words = explode(', ', Random::wordList(2, 4));
            $this->assertGreaterThanOrEqual(2, count($words));
            $this->assertLessThanOrEqual(4, count($words));
            $this->assertMatchesRegularExpression('/^[A-Z][a-z]*$/', $words[0]);
        }

        $this->assertCount(3, explode('|', Random::wordList(3, 3, '|')));
        $this->assertMatchesRegularExpression('/^[a-z]+( [a-z]+){2}$/', Random::wordList(3, 3, ' ', false));
        // invalid counts are corrected
        $this->assertCount(1, explode(', ', Random::wordList(0, -1)));
    }

    public function testHtmlParagraph(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $paragraph = Random::htmlParagraph(2, 3);
            $sentences = substr_count($paragraph, '. ') + 1;
            $this->assertGreaterThanOrEqual(2, $sentences);
            $this->assertLessThanOrEqual(3, $sentences);
            $this->assertStringEndsWith('.', $paragraph);
            $this->assertValidHtml($paragraph);
        }
    }

    public function testHtmlList(): void
    {
        $list = Random::htmlList(2, 2);
        $this->assertMatchesRegularExpression('/^<ul>(<li>[A-Z][a-z]*( [a-z]+){2,5}<\/li>){2}<\/ul>$/', $list);
    }

    public function testHtmlTable(): void
    {
        $table = Random::htmlTable(2, 2);
        $this->assertStringStartsWith('<table', $table);
        // a header row and 2 rows
        $this->assertSame(3, substr_count($table, '<tr>'));
        $this->assertValidHtml($table);
    }

    public function testHtml(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $html = Random::html(1, 2);
            $this->assertStringStartsWith("<html lang='en'><body>", $html);
            $this->assertStringEndsWith('</body></html>', $html);
            // there's always an image
            $this->assertStringContainsString('<img ', $html);
            $this->assertValidHtml($html);
        }
    }

    public function testUserAgent(): void
    {
        $this->assertStringStartsWith('Mozilla/5.0 (', Random::userAgent());
    }

    private function assertValidHtml(string $html): void
    {
        $previous = libxml_use_internal_errors(true);
        $this->assertNotFalse(simplexml_load_string("<root>$html</root>"), $html);
        $this->assertSame([], libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}
