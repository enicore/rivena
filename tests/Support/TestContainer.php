<?php
namespace Tests\Support;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * A minimal PSR-11 container whose services the tests can replace.
 */
class TestContainer implements ContainerInterface
{
    private static ?TestContainer $instance = null;
    private array $services = [];

    public static function instance(): TestContainer
    {
        return self::$instance ??= new TestContainer();
    }

    public function set(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function clear(): void
    {
        $this->services = [];
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class("Service $id not found.") extends RuntimeException implements NotFoundExceptionInterface {};
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
