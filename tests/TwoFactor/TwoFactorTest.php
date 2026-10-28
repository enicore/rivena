<?php
namespace Tests\TwoFactor;

use Enicore\Rivena\Classes\Crypt;
use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\RecoveryCodes;
use Enicore\Rivena\TwoFactor\Totp;
use Enicore\Rivena\TwoFactor\TwoFactor;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\Database;
use Tests\Support\TestConfig;

#[CoversClass(TwoFactor::class)]
class TwoFactorTest extends TestCase
{
    private const string SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        TestConfig::load();
        Database::create();
        User::forceCreate(['id' => 1, 'email' => 'a@example.com', 'password' => 'x', 'created_at' => 1]);
        User::forceCreate(['id' => 2, 'email' => 'b@example.com', 'password' => 'x', 'created_at' => 1]);
        time() % 30 === 29 && sleep(1);
    }

    private function code(int $steps = 0): string
    {
        return Totp::code(Totp::base32Decode(self::SECRET), intdiv(time(), 30) + $steps);
    }

    public function testMethods(): void
    {
        // both by default
        $this->assertSame(['totp', 'webauthn'], array_keys(TwoFactor::methods()));
        $this->assertInstanceOf(Totp::class, TwoFactor::methods()['totp']);

        // the unknown ones are ignored
        TestConfig::load(['two_factor_methods' => ['unknown', 'totp', 'recovery']]);
        $this->assertSame(['totp'], array_keys(TwoFactor::methods()));

        TestConfig::load(['two_factor_methods' => []]);
        $this->assertSame([], TwoFactor::methods());
        // still a method, for the users who have it
        $this->assertInstanceOf(Totp::class, TwoFactor::method('totp'));

        TestConfig::load(['two_factor_methods' => ['webauthn', 'totp'], 'site_url' => 'https://example.com']);
        $this->assertSame(['webauthn', 'totp'], array_keys(TwoFactor::methods()));
        $this->assertInstanceOf(RecoveryCodes::class, TwoFactor::method('recovery'));
        $this->assertNull(TwoFactor::method('unknown'));
    }

    public function testRequired(): void
    {
        $this->assertFalse(TwoFactor::required());

        TestConfig::load(['two_factor_required' => true]);
        $this->assertTrue(TwoFactor::required());

        // nothing to set up
        TestConfig::load(['two_factor_required' => true, 'two_factor_methods' => []]);
        $this->assertFalse(TwoFactor::required());
    }

    /**
     * The first method brings the recovery codes; the next ones and setting it up again don't change them.
     */
    public function testEnable(): void
    {
        $this->assertFalse(TwoFactor::enabled(1));
        $codes = TwoFactor::enable(1, 'totp', ['secret' => self::SECRET]);

        $this->assertCount(RecoveryCodes::COUNT, $codes);
        $this->assertTrue(TwoFactor::enabled(1));
        $this->assertFalse(TwoFactor::enabled(2));
        $this->assertSame(['totp' => ['secret' => self::SECRET], 'recovery' => ['codes' => $codes]],
            TwoFactor::userMethods(1));

        $this->assertNull(TwoFactor::enable(1, 'totp', ['secret' => 'AAAA']));
        $this->assertSame(['totp' => ['secret' => 'AAAA'], 'recovery' => ['codes' => $codes]],
            TwoFactor::userMethods(1));
    }

    /**
     * The data is encrypted with app_key: the secret isn't in the database.
     */
    public function testEncrypted(): void
    {
        TwoFactor::enable(1, 'totp', ['secret' => self::SECRET]);
        $row = DB::table('user_two_factor')->where('method', 'totp')->value('data');

        $this->assertStringNotContainsString(self::SECRET, $row);
        $this->assertSame(['secret' => self::SECRET], Crypt::decrypt($row));
    }

    /**
     * The user's methods still count when they're no longer in the config (only new setups are), in the config's order
     * first; unknown ones don't, nor the recovery codes alone.
     */
    public function testUserMethods(): void
    {
        TwoFactor::enable(1, 'totp', ['secret' => self::SECRET]);
        DB::table('user_two_factor')->insert(['user_id' => 1, 'method' => 'unknown',
            'data' => Crypt::encrypt([]), 'created_at' => 1]);
        $this->assertSame(['totp', 'recovery'], array_keys(TwoFactor::userMethods(1)));

        TestConfig::load(['two_factor_methods' => []]);
        $this->assertSame(['totp', 'recovery'], array_keys(TwoFactor::userMethods(1)));
        $this->assertTrue(TwoFactor::enabled(1));

        TestConfig::load(['two_factor_methods' => ['webauthn', 'totp'], 'site_url' => 'https://example.com']);
        DB::table('user_two_factor')->insert(['user_id' => 1, 'method' => 'webauthn',
            'data' => Crypt::encrypt(['keys' => []]), 'created_at' => 1]);
        $this->assertSame(['webauthn', 'totp', 'recovery'], array_keys(TwoFactor::userMethods(1)));

        DB::table('user_two_factor')->where('method', '!=', 'recovery')->delete();
        $this->assertSame([], TwoFactor::userMethods(1));
        $this->assertFalse(TwoFactor::enabled(1));
    }

    /**
     * Removing the last method removes the recovery codes too.
     */
    public function testDisable(): void
    {
        TwoFactor::enable(1, 'totp', ['secret' => self::SECRET]);
        TwoFactor::enable(2, 'totp', ['secret' => self::SECRET]);
        TwoFactor::disable(1, 'totp');

        $this->assertFalse(TwoFactor::enabled(1));
        $this->assertSame(0, DB::table('user_two_factor')->where('user_id', 1)->count());
        $this->assertTrue(TwoFactor::enabled(2));
    }

    public function testRenewRecoveryCodes(): void
    {
        $old = TwoFactor::enable(1, 'totp', ['secret' => self::SECRET]);
        $new = TwoFactor::renewRecoveryCodes(1);

        $this->assertCount(RecoveryCodes::COUNT, $new);
        $this->assertNotSame($old, $new);
        $this->assertSame($new, TwoFactor::userMethods(1)['recovery']['codes']);
        $this->assertFalse(TwoFactor::verify(1, 'recovery', $old[0]));
        $this->assertTrue(TwoFactor::verify(1, 'recovery', $new[0]));
    }

    public function testReset(): void
    {
        TwoFactor::enable(1, 'totp', ['secret' => self::SECRET]);
        TwoFactor::enable(2, 'totp', ['secret' => self::SECRET]);
        TwoFactor::reset(1);

        $this->assertSame([], TwoFactor::userMethods(1));
        $this->assertTrue(TwoFactor::enabled(2));
    }

    /**
     * Verifying saves the method's changed data: a code works once.
     */
    public function testVerify(): void
    {
        $codes = TwoFactor::enable(1, 'totp', ['secret' => self::SECRET]);

        $this->assertTrue(TwoFactor::verify(1, 'totp', $this->code()));
        $this->assertFalse(TwoFactor::verify(1, 'totp', $this->code()));
        $this->assertTrue(TwoFactor::verify(1, 'totp', $this->code(1)));
        $this->assertFalse(TwoFactor::verify(1, 'totp', $this->code(5)));

        $this->assertTrue(TwoFactor::verify(1, 'recovery', $codes[3]));
        $this->assertFalse(TwoFactor::verify(1, 'recovery', $codes[3]));
        $this->assertCount(RecoveryCodes::COUNT - 1, TwoFactor::userMethods(1)['recovery']['codes']);

        // another user's, a method the user doesn't have, an unknown or removed one
        $this->assertFalse(TwoFactor::verify(2, 'totp', $this->code(-1)));
        $this->assertFalse(TwoFactor::verify(2, 'recovery', $codes[4]));
        $this->assertFalse(TwoFactor::verify(1, 'unknown', $codes[4]));

        // a method no longer in the config still works
        TestConfig::load(['two_factor_methods' => []]);
        $this->assertTrue(TwoFactor::verify(1, 'recovery', $codes[4]));
    }
}
