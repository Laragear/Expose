<?php

declare(strict_types=1);

namespace Tests;

use Closure;
use Laragear\Expose\Container\Container;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;
use function array_map;
use function array_merge;
use function array_reduce;
use function class_exists;
use const DIRECTORY_SEPARATOR;

/**
 * Base test case providing helpers shared across the test suite.
 */
abstract class TestCase extends BaseTestCase
{
    protected ?Container $app = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = Container::getInstance();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Container::setInstance($this->app = null);

        Mockery::close();
    }

    public static function providesArchAndOs(): iterable
    {
        $arch = ['arm64', 'amd64'];
        $os = ['Windows', 'Linux', 'Darwin'];

        return array_reduce($os, static function ($carry, $o) use ($arch): array {
            return array_merge($carry, array_map(static function ($a) use ($o): array {
                return [$o, $a];
            }, $arch));
        }, []);
    }

    /**
     * Returns the absolute path to the test fixtures directory.
     */
    protected function fixturesPath(string $relative = ''): string
    {
        $base = __DIR__.DIRECTORY_SEPARATOR.'Fixtures';

        return $relative !== '' ? $base.DIRECTORY_SEPARATOR.ltrim($relative, '/\\') : $base;
    }

    /**
     * Creates a temporary directory, runs a callback with its path, then removes it.
     */
    protected function withTempDir(callable $callback): void
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'expose-test-'.uniqid('', true);

        mkdir($dir, 0755, true);

        try {
            $callback($dir);
        } finally {
            $this->removeDirectory($dir);
        }
    }

    /**
     * Recursively removes a directory and all its contents.
     */
    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = array_diff(scandir($path), ['.', '..']);

        foreach ($items as $item) {
            $full = $path.DIRECTORY_SEPARATOR.$item;

            is_dir($full) ? $this->removeDirectory($full) : unlink($full);
        }

        rmdir($path);
    }

    /**
     * Writes a file into a temporary directory and returns its path.
     * Creates intermediate directories as needed.
     */
    protected function writeFile(string $dir, string $name, string $contents): string
    {
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        $parent = dirname($path);

        if (!is_dir($parent)) {
            mkdir($parent, 0755, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Mocks a service.
     *
     * @template TMocked
     *
     * @param  class-string<TMocked>  $service
     * @param  (\Closure(TMocked&\Mockery\MockInterface):void)|null  $mock
     * @return TMocked&\Mockery\MockInterface
     */
    protected function mock(string $service, ?Closure $mock = null): MockInterface
    {
        $mock ??= static function (MockInterface $mock): MockInterface {
            return $mock;
        };

        $app = Container::getInstance();

        $instance = $app->has($service) ? $app->get($service) : Mockery::mock($service);

        $app->instance($service, $mock($instance) ?? $instance);

        return $instance;
    }
}
