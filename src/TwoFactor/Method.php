<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\TwoFactor;

use Enicore\Rivena\Models\User;

/**
 * A two-factor authentication method (TwoFactor::METHODS). Its data is an array that TwoFactor keeps encrypted in
 * user_two_factor: setUp() creates it, verify() checks a user's input with it. A method whose login starts in the
 * browser (WebAuthn) has a challenge(); one with several keys (WebAuthn's) has keys() and removeKey().
 *
 * @package Enicore\Rivena
 */
abstract class Method
{
    /**
     * Returns the method's name for the users, e.g. "Authenticator app".
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Returns what the method is, for the account page.
     *
     * @return string
     */
    abstract public function description(): string;

    /**
     * Starts setting up the method for the user, whose data it is (null if the user doesn't have the method yet):
     * returns ['data' => the method's data, 'page' => what the setup page shows (e.g. the QR code)]. The data is saved
     * once verify() accepts the user's input with it.
     *
     * @param User $user
     * @param array|null $data
     * @return array
     */
    abstract public function setUp(User $user, ?array $data): array;

    /**
     * Returns the data, changed if the input changes it (e.g. a used code), when the input is right; null when it's
     * wrong.
     *
     * @param array $data
     * @param string $input
     * @return array|null
     */
    abstract public function verify(array $data, string $input): ?array;

    /**
     * Starts a login whose input comes from the browser: returns ['data' => the login's challenge (kept in the session,
     * added to the data for verify()), 'page' => what the browser needs], or null for the methods that don't need it.
     *
     * @param array $data
     * @return array|null
     */
    public function challenge(array $data): ?array
    {
        return null;
    }

    /**
     * Returns the keys, id => name, of a method with several (set up one at a time, removed one at a time), or null.
     *
     * @param array $data
     * @return array|null
     */
    public function keys(array $data): ?array
    {
        return null;
    }

    /**
     * Returns the user's data (empty without the method) with a new setup's (verified), for a method with several keys
     * (keys()): the new key added. The setup's data by default.
     *
     * @param array $data
     * @param array $setup
     * @return array
     */
    public function add(array $data, array $setup): array
    {
        return $setup;
    }

    /**
     * Returns the data without the key (keys()).
     *
     * @param array $data
     * @param string $id
     * @return array
     */
    public function removeKey(array $data, string $id): array
    {
        return $data;
    }
}
