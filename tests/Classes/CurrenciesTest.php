<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use Enicore\Rivena\Classes\Currencies;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Enicore\Rivena\Classes\Currencies::class)]
class CurrenciesTest extends TestCase
{
    public function testGetCurrenciesReturnsAllCurrencies(): void
    {
        $currencies = Currencies::getCurrencies();
        $this->assertIsArray($currencies, 'getCurrencies should return an array');
        $this->assertNotEmpty($currencies, 'getCurrencies should return a non-empty array');
        $this->assertArrayHasKey('USD', $currencies, 'getCurrencies should include USD');
        $this->assertArrayHasKey('EUR', $currencies, 'getCurrencies should include EUR');
    }

    public function testGetCurrenciesFiltersByCodes(): void
    {
        $currencies = Currencies::getCurrencies(['USD', 'EUR']);
        $this->assertCount(2, $currencies, 'getCurrencies should return only the requested currencies');
        $this->assertArrayHasKey('USD', $currencies, 'getCurrencies should include USD');
        $this->assertArrayHasKey('EUR', $currencies, 'getCurrencies should include EUR');
    }

    public function testGetCurrenciesReturnsLocalizedNames(): void
    {
        $currencies = Currencies::getCurrencies(['USD'], true);
        $this->assertArrayHasKey('USD', $currencies, 'getCurrencies should include USD');
        $this->assertEquals('US Dollar', $currencies['USD'][1], 'getCurrencies should return the English name');
    }

    public function testGetCodes(): void
    {
        $codes = Currencies::getCodes();
        $this->assertIsArray($codes, 'getCodes should return an array');
        $this->assertContains('USD', $codes, 'getCodes should include USD');
        $this->assertContains('EUR', $codes, 'getCodes should include EUR');
    }

    public function testGetSymbol(): void
    {
        $symbol = Currencies::getSymbol('USD');
        $this->assertEquals('$', $symbol, 'getSymbol should return the correct symbol for USD');

        $symbol = Currencies::getSymbol('XYZ');
        $this->assertFalse($symbol, 'getSymbol should return false for an invalid currency code');
    }

    public function testGetNameInEnglish(): void
    {
        $name = Currencies::getName('USD', false);
        $this->assertEquals('US Dollar', $name, 'getName should return the English name for USD');

        $name = Currencies::getName('XYZ', false);
        $this->assertFalse($name, 'getName should return false for an invalid currency code');
    }

    public function testGetNameLocalized(): void
    {
        $name = Currencies::getName('USD', true);
        $this->assertEquals('US Dollar', $name, 'getName should return the localized name for USD');
    }

    public function testCodeExistsValidCode(): void
    {
        $this->assertTrue(Currencies::codeExists('USD'), 'codeExists should return true for a valid currency code');
    }

    public function testCodeExistsInvalidCode(): void
    {
        $this->assertFalse(Currencies::codeExists('XYZ'), 'codeExists should return false for an invalid currency code');
    }
}
