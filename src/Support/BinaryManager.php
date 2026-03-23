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
    protected string $binariesDir = '.expose'.DIRECTORY_SEPARATOR.'bin';

    /**
     * Create a new Binary Manager instance.
     */
    public function __construct(protected File $file, protected ProcessFactory $process, string $projectRoot)
    {
        $this->binariesDir = $projectRoot.DIRECTORY_SEPARATOR.$this->binariesDir;
    }

    /**
     * Returns the absolute path for a named binary inside the local bin directory.
     */
    public function binPath(string $binary): string
    {
        $suffix = $this->process->isWindows() ? '.exe' : '';

        return $this->binariesDir.DIRECTORY_SEPARATOR.$binary.$suffix;
    }

    /**
     * Returns true when the binary is found either on PATH or in the local bin directory.
     */
    public function isInstalled(string $binary): bool
    {
        return $this->findOnPath($binary) !== null
            || $this->file->exists($this->binPath($binary));
    }

    /**
     * Returns true when the `npm` executable is available on PATH.
     */
    public function isNpmAvailable(): bool
    {
        return $this->findOnPath('npm') !== null;
    }

    /**
     * Searches the system PATH for a binary and returns its absolute path, or null.
     */
    public function findOnPath(string $binary): ?string
    {
        return $this->file->findOnPath($binary);
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

        $process = $this->process->command('curl', '-fsSl', '-o', $destination, $url)->process();
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException("Failed to download [$binary] from [$url]: ".$process->getErrorOutput());
        }

        if ($this->process->isUnix()) {
            $this->file->chmod($destination, 0755);
        }
    }

    /**
     * Installs a package globally using NPM.
     */
    public function installViaNpm(string $package): void
    {
        $process = $this->process->command('npm', 'install', '-g', $package)->process();
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException("Failed to install [$package] via NPM: ".$process->getErrorOutput());
        }
    }

    /**
     * Uninstalls a globally installed NPM package.
     */
    public function uninstallViaNpm(string $package): void
    {
        $this->process
            ->command('npm', 'uninstall', '-g', $package)
            ->setTimeout(120)
            ->run();
    }

    /**
     * Deletes the locally managed binary from the bin directory if it exists.
     */
    public function removeBinary(string $binary): void
    {
        $path = $this->binPath($binary);

        if ($this->file->exists($path)) {
            $this->file->delete($path);
        }
    }

    /**
     * Returns the command string for a binary, preferring the locally managed copy over PATH.
     */
    public function resolveCommand(string $binary): string
    {
        $localPath = $this->binPath($binary);

        return $this->file->exists($localPath) ? $localPath : $binary;
    }

    /**
     * Creates the local bin directory when it does not already exist.
     */
    protected function ensureBinDirExists(): void
    {
        if ($this->file->isNotDir($this->binariesDir)) {
            $this->file->makeDir($this->binariesDir);
        }
    }
}
