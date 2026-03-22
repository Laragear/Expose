<?php

declare(strict_types=1);

namespace Tests\Support;

use Laragear\Expose\Support\BinaryManager;
use Tests\TestCase;
use const DIRECTORY_SEPARATOR;

/** Tests binary path resolution, installation checks, and removal logic. */
class BinaryManagerTest extends TestCase
{
    public function test_bin_dir_returns_correct_path(): void
    {
        $this->withTempDir(function (string $dir): void {
            $manager = new BinaryManager($dir);

            static::assertSame(
                $dir.DIRECTORY_SEPARATOR.'.expose'.DIRECTORY_SEPARATOR.'bin',
                $manager->binDir(),
            );
        });
    }

    public function test_bin_path_appends_exe_on_windows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows-only test.');
        }

        $manager = new BinaryManager(DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'project');

        static::assertStringEndsWith('.exe', $manager->binPath('ngrok'));
    }

    public function test_bin_path_has_no_extension_on_unix(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Unix-only test.');
        }

        $manager = new BinaryManager('/tmp/project');

        static::assertStringEndsWith('ngrok', $manager->binPath('ngrok'));
    }

    public function test_is_installed_returns_true_when_binary_exists_locally(): void
    {
        $this->withTempDir(function (string $dir): void {
            $manager = new BinaryManager($dir);
            $binPath = $manager->binPath('fakebinary');

            mkdir(dirname($binPath), 0755, true);
            file_put_contents($binPath, '#!/bin/sh');

            static::assertTrue($manager->isInstalled('fakebinary'));
        });
    }

    public function test_is_installed_returns_false_when_binary_absent(): void
    {
        $this->withTempDir(function (string $dir): void {
            $manager = new BinaryManager($dir);

            // Use a name that will never exist on PATH
            static::assertFalse($manager->isInstalled('__nonexistent_binary_xyz__'));
        });
    }

    public function test_remove_binary_deletes_local_file(): void
    {
        $this->withTempDir(function (string $dir): void {
            $manager = new BinaryManager($dir);
            $binPath = $manager->binPath('fakebinary');

            mkdir(dirname($binPath), 0755, true);
            file_put_contents($binPath, '#!/bin/sh');

            static::assertFileExists($binPath);

            $manager->removeBinary('fakebinary');

            static::assertFileDoesNotExist($binPath);
        });
    }

    public function test_remove_binary_is_noop_when_file_absent(): void
    {
        $this->withTempDir(function (string $dir): void {
            $manager = new BinaryManager($dir);

            // Should not throw even when the file does not exist
            $manager->removeBinary('__nonexistent__');
            static::assertTrue(true);
        });
    }

    public function test_resolve_command_returns_local_path_when_binary_exists(): void
    {
        $this->withTempDir(function (string $dir): void {
            $manager = new BinaryManager($dir);
            $binPath = $manager->binPath('fakebinary');

            mkdir(dirname($binPath), 0755, true);
            file_put_contents($binPath, '#!/bin/sh');

            static::assertSame($binPath, $manager->resolveCommand('fakebinary'));
        });
    }

    public function test_resolve_command_returns_binary_name_when_no_local_copy(): void
    {
        $this->withTempDir(function (string $dir): void {
            $manager = new BinaryManager($dir);

            static::assertSame('ngrok', $manager->resolveCommand('ngrok'));
        });
    }
}
