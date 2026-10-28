<?php
/**
 * Enicore Rivena.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Rivena\Classes;

use Enicore\Rivena\Exceptions\InputException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Routing\RouteContext;

/**
 * Slim's request with getters for the posted values (form or JSON). The routes get it instead of Slim's:
 *
 * $name = $request->getString('name', 100);
 * $count = $request->getInt('count', default: 1);
 *
 * A missing (without a default) or invalid value throws an InputException with a message for the user.
 *
 * @package Enicore\Rivena
 */
class Request extends SlimRequest
{
    /**
     * Returns a Rivena request made from another request (Slim's from the globals).
     *
     * @param ServerRequestInterface $request
     * @return Request
     */
    public static function from(ServerRequestInterface $request): Request
    {
        $self = new Request($request->getMethod(), $request->getUri(), new Headers($request->getHeaders()),
            $request->getCookieParams(), $request->getServerParams(), $request->getBody(),
            $request->getUploadedFiles());

        return $self->withParsedBody($request->getParsedBody());
    }

    /**
     * Returns the path of the named route, with the app's base path, e.g. urlFor('user', ['id' => 5]).
     *
     * @param string $routeName
     * @param array $data
     * @param array $queryParams
     * @return string
     */
    public function urlFor(string $routeName, array $data = [], array $queryParams = []): string
    {
        return RouteContext::fromRequest($this)->getRouteParser()->urlFor($routeName, $data, $queryParams);
    }

    /**
     * Returns the posted value as it is, or the default if it's missing.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data()[$key] ?? $default;
    }

    /**
     * Returns true if the posted value exists and isn't null.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data()[$key]);
    }

    /**
     * Returns all the posted values.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data();
    }

    /**
     * Returns the posted value as a trimmed string. A missing value (or an empty one with allowEmpty false) returns the
     * default, or throws without one; a value longer than maxLength (characters) throws.
     *
     * @param string $key
     * @param int|null $maxLength
     * @param string|null $default
     * @param bool $allowEmpty
     * @return string
     * @throws InputException
     */
    public function getString(string $key, ?int $maxLength = null, ?string $default = null,
                              bool $allowEmpty = true): string
    {
        $value = $this->data()[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            $value = (string)$value;
        }

        if ($value !== null && !is_string($value)) {
            throw new InputException("Invalid value: $key", $key);
        }

        $value = $value === null ? null : trim($value);

        if ($value === null || (!$allowEmpty && $value === '')) {
            return $this->missing($key, $default);
        }

        // not cut: the user would get a success, and something else than was sent saved
        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            throw new InputException("Too long: $key", $key);
        }

        return $value;
    }

    /**
     * Returns the posted value as an integer. A missing or empty value returns the default, or throws without one.
     *
     * @param string $key
     * @param int|null $default
     * @return int
     * @throws InputException
     */
    public function getInt(string $key, ?int $default = null): int
    {
        $value = $this->scalar($key);

        if ($value === null) {
            return $this->missing($key, $default);
        }

        if (($result = filter_var($value, FILTER_VALIDATE_INT)) === false) {
            throw new InputException("Invalid number: $key", $key);
        }

        return $result;
    }

    /**
     * Returns the posted value as a float. A missing or empty value returns the default, or throws without one.
     *
     * @param string $key
     * @param float|null $default
     * @return float
     * @throws InputException
     */
    public function getFloat(string $key, ?float $default = null): float
    {
        $value = $this->scalar($key);

        if ($value === null) {
            return $this->missing($key, $default);
        }

        if (!is_numeric($value)) {
            throw new InputException("Invalid number: $key", $key);
        }

        return (float)$value;
    }

    /**
     * Returns the posted value as a boolean (true, 1, "on", "yes"... or false, 0, "off", "no"...). A missing or empty
     * value returns the default, or throws without one; unchecked checkboxes send nothing.
     *
     * @param string $key
     * @param bool|null $default
     * @return bool
     * @throws InputException
     */
    public function getBool(string $key, ?bool $default = null): bool
    {
        $value = $this->scalar($key);

        if ($value === null) {
            return $this->missing($key, $default);
        }

        if (($result = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)) === null) {
            throw new InputException("Invalid value: $key", $key);
        }

        return $result;
    }

    /**
     * Returns the posted value as a valid, trimmed email address. A missing or empty value returns the default, or
     * throws without one.
     *
     * @param string $key
     * @param string|null $default
     * @return string
     * @throws InputException
     */
    public function getEmail(string $key, ?string $default = null): string
    {
        $value = $this->getString($key, null, '', false);

        if ($value === '') {
            return $this->missing($key, $default);
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InputException("Invalid email address: $key", $key);
        }

        return $value;
    }

    /**
     * Returns the posted value as an array. A missing value returns the default, or throws without one.
     *
     * @param string $key
     * @param array|null $default
     * @return array
     * @throws InputException
     */
    public function getArray(string $key, ?array $default = null): array
    {
        $value = $this->data()[$key] ?? null;

        if ($value === null) {
            return $this->missing($key, $default);
        }

        if (!is_array($value)) {
            throw new InputException("Invalid value: $key", $key);
        }

        return $value;
    }

    /**
     * Returns the posted scalar value, or null if it's missing or an empty string.
     *
     * @param string $key
     * @return string|int|float|bool|null
     * @throws InputException
     */
    private function scalar(string $key): string|int|float|bool|null
    {
        $value = $this->data()[$key] ?? null;

        if ($value !== null && !is_scalar($value)) {
            throw new InputException("Invalid value: $key", $key);
        }

        $value = is_string($value) ? trim($value) : $value;
        return $value === '' ? null : $value;
    }

    /**
     * Returns the default for a missing value, or throws if there's none.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     * @throws InputException
     */
    private function missing(string $key, mixed $default): mixed
    {
        if ($default === null) {
            throw new InputException("Missing value: $key", $key);
        }

        return $default;
    }

    /**
     * Returns the parsed body: the posted form or JSON values.
     *
     * @return array
     */
    private function data(): array
    {
        $data = $this->getParsedBody();
        return is_array($data) ? $data : [];
    }
}
