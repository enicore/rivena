<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\GeoIp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeoIp::class)]
class GeoIpTest extends TestCase
{
    public function testCountry(): void
    {
        // MaxMind's test addresses, and Google's DNS
        $this->assertSame('GB', GeoIp::country('81.2.69.142'));
        $this->assertSame('US', GeoIp::country('8.8.8.8'));
        $this->assertSame('United Kingdom', GeoIp::countryName('81.2.69.142'));
        $this->assertSame('Vereinigtes Königreich', GeoIp::countryName('81.2.69.142', 'de'));
        $this->assertNotNull(GeoIp::country('2001:4860:4860::8888'));
    }

    public function testNoCountry(): void
    {
        foreach (['127.0.0.1', '10.0.0.1', '192.168.1.1', '::1', '0.0.0.0', 'not an ip', ''] as $ip) {
            $this->assertNull(GeoIp::country($ip), $ip);
        }
        $this->assertNull(GeoIp::countryName('10.0.0.1'));
    }
}
