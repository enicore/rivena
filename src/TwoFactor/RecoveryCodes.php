<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\TwoFactor;

use Enicore\Rivena\Classes\Crypt;
use Enicore\Rivena\Models\User;

/**
 * The recovery codes, for when the user can't use the other methods: TwoFactor adds them with the user's first
 * method. Each code works once; they're typed in any case, with or without the dash.
 *
 * @package Enicore\Rivena
 */
final class RecoveryCodes extends Method
{
    public const int COUNT = 10;
    // without 0, O, 1 and I, which are easy to mix up
    private const string CHARACTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Returns the method's name.
     *
     * @return string
     */
    public function name(): string
    {
        return 'Recovery codes';
    }

    /**
     * Returns what the method is.
     *
     * @return string
     */
    public function description(): string
    {
        return 'Single-use codes for when you can\'t use your other methods.';
    }

    /**
     * Returns new codes, as the data and for the page.
     *
     * @param User $user
     * @param array|null $data
     * @return array
     */
    public function setUp(User $user, ?array $data): array
    {
        $codes = self::generate();

        return ['data' => ['codes' => $codes], 'page' => ['codes' => $codes]];
    }

    /**
     * Returns the data without the code when it's one of the codes, or null.
     *
     * @param array $data
     * @param string $input
     * @return array|null
     */
    public function verify(array $data, string $input): ?array
    {
        $input = preg_replace('/[^A-Z0-9]/', '', strtoupper($input));
        $codes = $data['codes'];

        foreach ($codes as $index => $code) {
            if (hash_equals(str_replace('-', '', $code), $input)) {
                unset($codes[$index]);
                return ['codes' => array_values($codes)] + $data;
            }
        }

        return null;
    }

    /**
     * Returns COUNT new codes, like "K7M2P-X9QRT".
     *
     * @return array
     */
    public static function generate(): array
    {
        $codes = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $codes[] = Crypt::generateRandomToken(5, self::CHARACTERS) . '-' .
                Crypt::generateRandomToken(5, self::CHARACTERS);
        }

        return $codes;
    }
}
