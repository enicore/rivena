<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Classes\Languages;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Enicore\Rivena\Classes\Languages::class)]
class LanguagesTest extends TestCase
{
    public function testGetLanguagesReturnsAllLanguages(): void
    {
        $languages = Languages::getLanguages();
        $this->assertIsArray($languages, 'getLanguages should return an array');
        $this->assertNotEmpty($languages, 'getLanguages should return a non-empty array');
        $this->assertArrayHasKey('en', $languages, 'getLanguages should include English (en)');
        $this->assertArrayHasKey('fr', $languages, 'getLanguages should include French (fr)');
    }

    public function testGetLanguagesFiltersByCodes(): void
    {
        $languages = Languages::getLanguages(['en', 'fr']);
        $this->assertCount(2, $languages, 'getLanguages should return only the requested languages');
        $this->assertArrayHasKey('en', $languages, 'getLanguages should include English (en)');
        $this->assertArrayHasKey('fr', $languages, 'getLanguages should include French (fr)');
    }

    public function testGetLanguagesReturnsLocalizedNames(): void
    {
        $languages = Languages::getLanguages(['en'], true);
        $this->assertArrayHasKey('en', $languages, 'getLanguages should include English (en)');
        $this->assertEquals('English', $languages['en'], 'getLanguages should return the localized name');
    }

    public function testGetCodes(): void
    {
        $codes = Languages::getCodes();
        $this->assertIsArray($codes, 'getCodes should return an array');
        $this->assertContains('en', $codes, 'getCodes should include English (en)');
        $this->assertContains('fr', $codes, 'getCodes should include French (fr)');
    }

    public function testGetNameInEnglish(): void
    {
        $name = Languages::getName('en', false);
        $this->assertEquals('English', $name, 'getName should return the English name for the language');
    }

    public function testGetNameLocalized(): void
    {
        $name = Languages::getName('fr', true);
        $this->assertEquals('Français', $name, 'getName should return the localized name for the language');
    }

    public function testGetNameInvalidCode(): void
    {
        $name = Languages::getName('xx');
        $this->assertFalse($name, 'getName should return false for an invalid language code');
    }

    public function testCodeExistsValidCode(): void
    {
        $this->assertTrue(Languages::codeExists('en'), 'codeExists should return true for a valid language code');
    }

    public function testCodeExistsInvalidCode(): void
    {
        $this->assertFalse(Languages::codeExists('xx'), 'codeExists should return false for an invalid language code');
    }
}
