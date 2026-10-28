<?php
namespace Tests\Classes;

use Enicore\Rivena\Classes\Crypt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestConfig;

#[CoversClass(\Enicore\Rivena\Classes\Crypt::class)]
class CryptTest extends TestCase
{
    public function testEncryptId(): void
    {
        TestConfig::load(['app_key' => 'key']);
        $token = Crypt::encryptId(12345);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22}$/', $token);
        $this->assertSame($token, Crypt::encryptId(12345));
        $this->assertNotSame($token, Crypt::encryptId(12346));
        $this->assertSame(12345, Crypt::decryptId($token));
        // 0 is an ID too, not "can't be decrypted"
        $this->assertSame(0, Crypt::decryptId(Crypt::encryptId(0)));

        // another key, changed and made-up tokens
        TestConfig::load(['app_key' => 'other']);
        $this->assertNull(Crypt::decryptId($token));
        TestConfig::load(['app_key' => 'key']);
        $this->assertNull(Crypt::decryptId(($token[0] === 'A' ? 'B' : 'A') . substr($token, 1)));
        $this->assertNull(Crypt::decryptId(''));
        $this->assertNull(Crypt::decryptId('AAAAAAAAAAAAAAAAAAAAAA'));
        TestConfig::load();
    }

    public function testEncryptDecrypt(): void
    {
        TestConfig::load(['app_key' => 'key']);

        foreach ([true, false, 0, 12345, 1.0, 0.1 + 0.2, '', 'text', "\xff\x00binary", null, ['a' => 1, 'b' => [2, 3]],
            str_repeat('compressed ', 100)] as $data) {
            $encrypted = Crypt::encrypt($data);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $encrypted);
            $this->assertSame($data, Crypt::decrypt($encrypted));
        }

        // objects come back as objects of their class
        $object = (object)['a' => 1, 'b' => new \ArrayObject([2, 3])];
        $this->assertEquals($object, Crypt::decrypt(Crypt::encrypt($object)));

        // compressed when that's smaller
        $this->assertLessThan(200, strlen(Crypt::encrypt(str_repeat('compressed ', 100))));

        // a random IV: the same data gives another string
        $this->assertNotSame(Crypt::encrypt('text'), Crypt::encrypt('text'));

        // a key given
        $this->assertSame('text', Crypt::decrypt(Crypt::encrypt('text', 'other'), 'other'));
        TestConfig::load();
    }

    #[DataProvider('undecryptable')]
    public function testDecryptFails(string $data): void
    {
        $this->expectException(RuntimeException::class);
        Crypt::decrypt($data, 'key');
    }

    public static function undecryptable(): array
    {
        $encrypted = Crypt::encrypt('text', 'key');

        return [
            'another key' => [Crypt::encrypt('text', 'other')],
            'changed' => [substr_replace($encrypted, $encrypted[20] === 'A' ? 'B' : 'A', 20, 1)],
            'empty' => [''],
            'made up' => ['AAAA'],
        ];
    }

    public function testGeneratePassword(): void
    {
        $password = Crypt::generatePassword(16);
        $this->assertIsString($password);
        $this->assertSame(16, strlen($password));
    }

    public function testGenerateRandomToken(): void
    {
        $token = Crypt::generateRandomToken(32);
        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));
    }

    public function testBaseEncodeDecode(): void
    {
        $number = 123456789;
        $encoded = Crypt::baseEncode($number, Crypt::BASE_62_CHARSET);
        $this->assertNotEmpty($encoded);

        $decoded = Crypt::baseDecode($encoded, Crypt::BASE_62_CHARSET);
        $this->assertSame($number, $decoded);

        $this->assertSame(PHP_INT_MAX, Crypt::baseDecode(Crypt::baseEncode(PHP_INT_MAX)));
        $this->assertSame(PHP_INT_MAX, Crypt::baseDecode(Crypt::baseEncode(PHP_INT_MAX, Crypt::BASE_92_CHARSET),
            Crypt::BASE_92_CHARSET));
        $this->assertSame('', Crypt::baseEncode(0));
        $this->assertSame('', Crypt::baseEncode(-5));
    }

    public function testBaseDecodeInvalid(): void
    {
        // empty, unknown characters, past PHP_INT_MAX (1y2p0ij32e8e7 in base 36)
        foreach (['', 'ab-c', 'ABC', '1y2p0ij32e8e8', 'zzzzzzzzzzzzzzzzzzz'] as $encoded) {
            $this->assertFalse(Crypt::baseDecode($encoded), $encoded);
        }
    }

    public function testTextToBinaryAndBinaryToText(): void
    {
        $text = "This is a test binary string.";
        $binary = Crypt::binaryToText($text);
        $decodedText = Crypt::textToBinary($binary);

        $this->assertSame($text, $decodedText);
    }
}
