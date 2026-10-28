<?php
namespace Tests\Classes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Enicore\Rivena\Classes\Text;

#[CoversClass(\Enicore\Rivena\Classes\Text::class)]
class TextTest extends TestCase
{
    public function testSizeToString(): void
    {
        $this->assertSame('1 B', Text::sizeToString(1));
        $this->assertSame('1.5 MB', Text::sizeToString(1500000));
        $this->assertSame('1 GB', Text::sizeToString(1000000000));
        // rounded up to the next unit, and the largest unit for the largest sizes
        $this->assertSame('1 MB', Text::sizeToString(999999));
        $this->assertSame('9223.4 PB', Text::sizeToString(PHP_INT_MAX));
        $this->assertSame('-', Text::sizeToString('invalid'));
        // floats too (getMaxFileUploadSize() can give one)
        $this->assertSame('999.9 B', Text::sizeToString(999.9));
    }

    public function testShortenString(): void
    {
        $this->assertSame('Short text', Text::shortenString('Short text', 20));
        $this->assertSame('This is a long...', Text::shortenString('This is a long string that exceeds the limit', 17));
        $this->assertSame('Edge-case...', Text::shortenString('Edge-case test', 10));
        // cut at characters, not bytes
        $this->assertSame('žžž...', Text::shortenString('žžžž', 3));
    }

    public function testRemoveSlash(): void
    {
        $this->assertSame('path/to/resource', Text::removeSlash('path/to/resource/'));
        $this->assertSame('path\\to\\resource', Text::removeSlash('path\\to\\resource\\'));
        $this->assertSame('no-slash', Text::removeSlash('no-slash'));
    }

    public function testAddSlash(): void
    {
        $this->assertSame('path/to/resource/', Text::addSlash('path/to/resource'));
        $this->assertSame('path\\to\\resource/', Text::addSlash('path\\to\\resource\\'));
        $this->assertSame('already/has/slash/', Text::addSlash('already/has/slash/'));
    }

    public function testStrToBool(): void
    {
        $this->assertTrue(Text::strToBool('true'));
        $this->assertFalse(Text::strToBool('false'));
        $this->assertFalse(Text::strToBool('invalid'));
        $this->assertFalse(Text::strToBool(''));
        $this->assertTrue(Text::strToBool('  true  ')); // Trimmed input
    }
}
