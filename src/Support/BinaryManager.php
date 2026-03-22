<?php

declare(strict_types=1);

namespace Laragear\Expose\Support;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use const DIRECTORY_SEPARATOR;

/**
 * Handles binary discovery, cURL download, NPM install, and removal for tunnel services.
 */
class BinaryManager
{
    /**
     * Relative path inside the project root where downloaded binaries are stored.
     */
    protected const string BIN_DIR = '.expose' . DIRECTORY_SEPARATOR . 'bin';

    /**
     * Create a new Binary Manager instance.
     */
    public function __construct(protected readonly string $projectRoot)
    {
        //
    }

    /**
     * Returns the absolute path to the local bin directory managed by Expose.
     */
    public function binDir(): string
    {
        return $this->projectRoot.DIRECTORY_SEPARATOR.self::BIN_DIR;
    }

    /**
     * Returns the absolute path for a named binary inside the local bin directory.
     */
    public function binPath(string $binary): string
    {
        $suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';

        return $this->binDir().DIRECTORY_SEPARATOR.$binary.$suffix;
    }

    /**
     * Returns true when the binary is found either on PATH or in the local bin directory.
     */
    public function isInstalled(string $binary): bool
    {
        return $this->findOnPath($binary) !== null
            || file_exists($this->binPath($binary));
    }

    /**
     * Returns true when the `npm` executable is available on PATH.
     */
    public function isNpmAvailable(): bool
    {
        return (new ExecutableFinder())->find('npm') !== null;
    }

    /**
     * Searches the system PATH for a binary and returns its absolute path, or null.
     */
    public function findOnPath(string $binary): ?string
    {
        return (new ExecutableFinder())->find($binary);
    }

    /**
     * Downloads a binary from the given URL using cURL and saves it locally.
     *
     * Sets executable permissions on non-Windows systems.
     */
    public function downloadViaCurl(string $url, string $binary): void
    {
        $this->ensureBinDirExists();

        $destination = $this->binPath($binary);

        $process = new Process(['curl', '-fsSL', '-o', $destination, $url]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException("Failed to download [{$binary}] from [{$url}]: ".$process->getErrorOutput());
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($destination, 0755);
        }
    }

    /**
     * Installs a package globally using NPM.
     */
    public function installViaNpm(string $package): void
    {
        $process = new Process(['npm', 'install', '-g', $package]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException("Failed to install [{$package}] via NPM: ".$process->getErrorOutput());
        }
    }

    /**
     * Uninstalls a globally installed NPM package.
     */
    public function uninstallViaNpm(string $package): void
    {
        $process = new Process(['npm', 'uninstall', '-g', $package]);
        $process->setTimeout(60);
        $process->run();
    }

    /**
     * Deletes the locally managed binary from the bin directory if it exists.
     */
    public function removeBinary(string $binary): void
    {
        $path = $this->binPath($binary);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Returns the command string for a binary, preferring the locally managed copy over PATH.
     */
    public function resolveCommand(string $binary): string
    {
        $localPath = $this->binPath($binary);

        return file_exists($localPath) ? $localPath : $binary;
    }

    /**
     * Creates the local bin directory when it does not already exist.
     */
    protected function ensureBinDirExists(): void
    {
        if (!is_dir($this->binDir())) {
            mkdir($this->binDir(), 0755, true);
        }
    }
}
