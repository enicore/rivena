<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by Request's getters for a missing or invalid value. The message can be shown to the user; getKey() is the
 * value's name.
 *
 * @package Enicore\Rivena
 */
class InputException extends InvalidArgumentException
{
    /**
     * Creates the exception with the message for the user and the key of the value.
     *
     * @param string $message
     * @param string $key
     */
    public function __construct(string $message, private readonly string $key = '')
    {
        parent::__construct($message);
    }

    /**
     * Returns the key of the missing or invalid value.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }
}
