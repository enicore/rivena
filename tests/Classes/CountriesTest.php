<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Classes\Countries;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Enicore\Rivena\Classes\Countries::class)]
class CountriesTest extends TestCase
{
    public function testGetCountriesReturnsAllCountries(): void
    {
        $countries = Countries::getCountries();
        $this->assertIsArray($countries, 'getCountries should return an array');
        $this->assertNotEmpty($countries, 'getCountries should return a non-empty array');
        $this->assertArrayHasKey('US', $countries, 'getCountries should include US in the result');
    }

    public function testGetCountriesFiltersByCodes(): void
    {
        $countries = Countries::getCountries(['US', 'GB']);
        $this->assertCount(2, $countries, 'getCountries should return only the requested countries');
        $this->assertArrayHasKey('US', $countries, 'getCountries should include US');
        $this->assertArrayHasKey('GB', $countries, 'getCountries should include GB');
    }

    public function testGetCountriesReturnsLocalizedNames(): void
    {
        $countries = Countries::getCountries(['US'], 'es');
        $this->assertArrayHasKey('US', $countries, 'getCountries should include US');
        $this->assertEquals('Estados Unidos', $countries['US'], 'getCountries should return the localized name in Spanish');
    }

    public function testGetCodes(): void
    {
        $codes = Countries::getCodes();
        $this->assertIsArray($codes, 'getCodes should return an array');
        $this->assertContains('US', $codes, 'getCodes should include US');
        $this->assertContains('GB', $codes, 'getCodes should include GB');
    }

    public function testGetNameInEnglish(): void
    {
        $name = Countries::getName('US', 'en');
        $this->assertEquals('United States', $name, 'getName should return the country name in English');
    }

    public function testGetNameInDifferentLanguage(): void
    {
        $name = Countries::getName('US', 'es');
        $this->assertEquals('Estados Unidos', $name, 'getName should return the country name in Spanish');
    }

    public function testGetNameInvalidCode(): void
    {
        $name = Countries::getName('XX');
        $this->assertEquals('', $name, 'getName should return an empty string for an invalid code');
    }

    public function testCodeExistsValidCode(): void
    {
        $this->assertTrue(Countries::codeExists('US'), 'codeExists should return true for a valid country code');
    }

    public function testCodeExistsInvalidCode(): void
    {
        $this->assertFalse(Countries::codeExists('XX'), 'codeExists should return false for an invalid country code');
    }
}
