<?php
/**
 * Enicore Rivena.
 * Copyright 2026 Enicore Solutions.
 */
namespace Enicore\Rivena\Exceptions;

use RuntimeException;

/**
 * Thrown by Auth::attempt() for a wrong email or password (code 401) or too many failed attempts (429). The message
 * can be shown to the user.
 *
 * @package Enicore\Rivena
 */
class LoginException extends RuntimeException
{
}
