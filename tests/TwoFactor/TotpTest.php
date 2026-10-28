<?php
namespace Tests\TwoFactor;

use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\Totp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestConfig;

#[CoversClass(Totp::class)]
class TotpTest extends TestCase
{
    private Totp $totp;
    private string $secret;

    protected function setUp(): void
    {
        TestConfig::load();
        $this->totp = new Totp();
        $this->secret = Totp::base32Encode('12345678901234567890');

        // not in a time step's last second, so the step doesn't change during a test
        time() % 30 === 29 && sleep(1);
    }

    // the secret's code for the time step from now
    private function code(int $steps = 0): string
    {
        return Totp::code('12345678901234567890', intdiv(time(), 30) + $steps);
    }

    /**
     * RFC 6238's SHA-1 test values (the last 6 of their 8 digits) and RFC 4648's base32.
     */
    public function testRfcValues(): void
    {
        foreach ([59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924',
                     2000000000 => '279037', 20000000000 => '353130'] as $time => $code) {
            $this->assertSame($code, Totp::code('12345678901234567890', intdiv($time, 30)));
        }

        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $this->secret);
        $this->assertSame('MZXW6YTBOI', Totp::base32Encode('foobar'));
        $this->assertSame('foobar', Totp::base32Decode('MZXW6YTBOI'));
        $this->assertSame('', Totp::base32Decode('MZXW6YTBO1'));
    }

    public function testSetUp(): void
    {
        $user = new User();
        $user->email = 'a@example.com';
        $setup = $this->totp->setUp($user, null);
        $secret = $setup['data']['secret'];

        // 20 random bytes, shown in groups of 4
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertSame(20, strlen(Totp::base32Decode($secret)));
        $this->assertSame(trim(chunk_split($secret, 4, ' ')), $setup['page']['secret']);
        $this->assertNotSame($secret, $this->totp->setUp($user, null)['data']['secret']);
        $this->assertStringStartsWith('<svg', $setup['page']['qr']);
        $this->assertSame(['secret'], array_keys($setup['data']));
    }

    /**
     * The current code works, and the one before and after it (clocks differ), but not further ones.
     */
    public function testVerify(): void
    {
        $data = ['secret' => $this->secret];
        $step = intdiv(time(), 30);

        $this->assertSame(['last_step' => $step] + $data, $this->totp->verify($data, $this->code()));
        $this->assertSame(['last_step' => $step - 1] + $data, $this->totp->verify($data, $this->code(-1)));
        $this->assertSame(['last_step' => $step + 1] + $data, $this->totp->verify($data, $this->code(1)));
        $this->assertNull($this->totp->verify($data, $this->code(-2)));
        $this->assertNull($this->totp->verify($data, $this->code(2)));

        // spaces are ignored, other characters aren't
        $this->assertNotNull($this->totp->verify($data, ' ' . substr($this->code(), 0, 3) . ' ' .
            substr($this->code(), 3)));
        $this->assertNull($this->totp->verify($data, $this->code() . '0'));
        $this->assertNull($this->totp->verify($data, substr($this->code(), 1)));
        $this->assertNull($this->totp->verify($data, ''));
        $this->assertNull($this->totp->verify($data, 'abcdef'));
    }

    /**
     * A code works once: the same one or an earlier one doesn't work again (RFC 6238, 5.2), a later one does.
     */
    public function testCodeWorksOnce(): void
    {
        $data = $this->totp->verify(['secret' => $this->secret], $this->code());

        $this->assertNull($this->totp->verify($data, $this->code()));
        $this->assertNull($this->totp->verify($data, $this->code(-1)));
        $this->assertNotNull($this->totp->verify($data, $this->code(1)));
    }
}
