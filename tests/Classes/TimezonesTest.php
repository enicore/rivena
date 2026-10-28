<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Rivena\Classes\Timezones;

#[CoversClass(\Enicore\Rivena\Classes\Timezones::class)]
class TimezonesTest extends TestCase
{
    public function testGetTimezonesWithDefaultParameters()
    {
        $timezones = Timezones::getTimezones();
        $this->assertIsArray($timezones);
        $this->assertNotEmpty($timezones);
    }

    public function testGetTimezonesWithExtendedTrue()
    {
        $timezones = Timezones::getTimezones(true);
        $this->assertIsArray($timezones);
        $this->assertNotEmpty($timezones);

        // UTC first, in both lists
        $this->assertSame(['UTC' => 'UTC'], array_slice($timezones, 0, 1));
        $this->assertSame(['UTC' => 'UTC'], array_slice(Timezones::getTimezones(), 0, 1));
    }

    public function testGetNameWithValidTimezone()
    {
        $timezone = 'Europe/Berlin';
        $localizedName = Timezones::getName($timezone, 'en');

        $this->assertIsString($localizedName);
        $this->assertNotEmpty($localizedName);
    }

    public function testGetNamePlaceholders()
    {
        // the longer placeholders aren't replaced as %tz_short or %tz_long plus the rest; Tokyo has no summer time
        $name = Timezones::getName('Asia/Tokyo', 'en',
            '%tz_short_gmt|%tz_long_gmt|%tz_long_generic|%tz_generic_location|%country|%offset');
        $this->assertSame('+0900|GMT+09:00|Japan Standard Time|Japan Time|Japan|+09:00', $name);
    }

    public function testGetNameWithInvalidTimezone()
    {
        $timezone = 'Invalid/Timezone';
        $localizedName = Timezones::getName($timezone, 'en');

        $this->assertEquals($timezone, $localizedName);
    }

    public function testGetUtcOffsetWithValidTimezone()
    {
        $timezone = 'Europe/London';
        $utcOffset = Timezones::getUtcOffset($timezone);

        $this->assertMatchesRegularExpression('/^[+-]\d{2}:\d{2}$/', $utcOffset);
    }

    public function testGetUtcOffsetWithInvalidTimezone()
    {
        $timezone = 'Invalid/Timezone';
        $utcOffset = Timezones::getUtcOffset($timezone);

        $this->assertEquals('Invalid Time Zone', $utcOffset);
    }

    public function testTimeZoneExistsWithValidTimezone()
    {
        $timezone = 'America/New_York';
        $exists = Timezones::timeZoneExists($timezone);

        $this->assertTrue($exists);
    }

    public function testTimeZoneExistsWithInvalidTimezone()
    {
        $timezone = 'Invalid/Timezone';
        $exists = Timezones::timeZoneExists($timezone);

        $this->assertFalse($exists);
    }

    public function testGetTimezoneDataWithDefaultParameters()
    {
        $data = Timezones::getTimezoneData();

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('US', $data);
        $this->assertContains('America/New_York', $data['US']);
    }

    public function testGetTimezoneDataWithExtendedTrue()
    {
        $data = Timezones::getTimezoneData(true);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function testCreateLocalizedNameWithValidData()
    {
        $reflection = new \ReflectionClass(Timezones::class);
        $method = $reflection->getMethod('createLocalizedName');

        $result = $method->invoke(null, 'Europe/Berlin', 'en', '%country (%offset)', 'DE');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('Germany', $result); // Assuming "Germany" is returned in English
    }

    public function testConvertTimeWithValidTimezones()
    {
        $result = Timezones::convertTime('2024-12-14 15:00:00', 'America/New_York', 'Europe/Berlin');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    public function testConvertTimeWithInvalidTimezones()
    {
        $result = Timezones::convertTime('2024-12-14 15:00:00', 'Invalid/Timezone', 'Europe/Berlin');

        $this->assertNull($result);
    }

    public function testGetCurrentTimeWithValidTimezone()
    {
        $result = Timezones::getCurrentTime('Europe/Berlin');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    public function testGetCurrentTimeWithInvalidTimezone()
    {
        $result = Timezones::getCurrentTime('Invalid/Timezone');

        $this->assertNull($result);
    }

    public function testGetTimezonesByCountryWithValidCountry()
    {
        $result = Timezones::getTimezonesByCountry('US');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertContains('America/New_York', $result);
        $this->assertSame($result, Timezones::getTimezonesByCountry('us'));
    }

    public function testGetTimezonesByCountryWithInvalidCountry()
    {
        $result = Timezones::getTimezonesByCountry('ZZ');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
