<?php
namespace Tests\TwoFactor;

use Enicore\Rivena\Models\User;
use Enicore\Rivena\TwoFactor\RecoveryCodes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecoveryCodes::class)]
class RecoveryCodesTest extends TestCase
{
    public function testGenerate(): void
    {
        $codes = RecoveryCodes::generate();

        $this->assertCount(RecoveryCodes::COUNT, $codes);
        $this->assertCount(RecoveryCodes::COUNT, array_unique($codes));

        foreach ($codes as $code) {
            // without the characters that are easy to mix up
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $code);
        }

        $setup = (new RecoveryCodes())->setUp(new User(), null);
        $this->assertSame($setup['data']['codes'], $setup['page']['codes']);
        $this->assertCount(RecoveryCodes::COUNT, $setup['data']['codes']);
    }

    /**
     * A code works once, in any case, with or without the dash and spaces.
     */
    public function testVerify(): void
    {
        $method = new RecoveryCodes();
        $data = ['codes' => ['ABCDE-FGHJK', 'LMNPQ-RSTUV', 'WXYZ2-34567']];

        $this->assertSame(['codes' => ['ABCDE-FGHJK', 'WXYZ2-34567']], $method->verify($data, 'LMNPQ-RSTUV'));
        $this->assertSame(['codes' => ['LMNPQ-RSTUV', 'WXYZ2-34567']], $method->verify($data, 'abcdefghjk'));
        $this->assertSame(['codes' => ['ABCDE-FGHJK', 'LMNPQ-RSTUV']], $method->verify($data, ' wxyz2 34567 '));

        $used = $method->verify($data, 'LMNPQ-RSTUV');
        $this->assertNull($method->verify($used, 'LMNPQ-RSTUV'));

        $this->assertNull($method->verify($data, 'ABCDE-FGHJ'));
        $this->assertNull($method->verify($data, 'ABCDE-FGHJKL'));
        $this->assertNull($method->verify($data, ''));
        $this->assertNull($method->verify($data, '-'));
        $this->assertNull($method->verify(['codes' => []], 'ABCDE-FGHJK'));
    }
}
