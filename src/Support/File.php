<?php

declare(strict_types=1);

namespace Laragear\Expose\Support;

use Symfony\Component\Process\ExecutableFinder;
use function app;
use function chmod;
use function file;
use function file_exists;
use function file_get_contents;
use function glob;

class File
{
    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Check if a file is missing.
     */
    public function missing(string $path): bool
    {
        return !file_exists($path);
    }

    /**
     * Check if a path exists and is a directory.
     */
    public function isDir(string $dir): bool
    {
        return is_dir($dir);
    }

    /**
     * Check if a path does not exist and is not a directory.
     */
    public function isNotDir(string $dir): bool
    {
        return !$this->isDir($dir);
    }

    /**
     * Returns the path where a command in PATH exists.
     */
    public function findOnPath(string $command): ?string
    {
        return app(ExecutableFinder::class)->find($command);
    }

    /**
     * Change the permissions of a given file or directory.
     */
    public function chmod(string $path, int $permissions): void
    {
        chmod($path, $permissions);
    }

    /**
     * Deletes a file.
     */
    public function delete(string $path): bool
    {
        return unlink($path);
    }

    /**
     * Creates a directory.
     */
    public function makeDir(string $dir, int $permissions = 0755, bool $recursive = true): bool
    {
        return mkdir($dir, $permissions, $recursive);
    }

    /**
     * Returns the contents of a file.
     */
    public function get(string $path): string|false
    {
        return file_get_contents($path);
    }

    /**
     * Returns the contents of a file as an array of lines
     */
    public function lines(string $path, int $param = 0): array|false
    {
        return file($path, $param); // @phpstan-ignore-line
    }

    /**
     * Find path names matching a pattern.
     */
    public function glob(string $string): array|false
    {
        return glob($string);
    }
}
