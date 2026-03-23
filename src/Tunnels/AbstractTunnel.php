<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Support\BinaryManager;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

;

/**
 * Provides shared behavior for all Tunnel implementations.
 */
abstract class AbstractTunnel implements InstallableTunnel
{
    /**
     * Indicates whether this tunnel is distributed as an NPM package.
     */
    protected bool $npmPackage = false;

    /**
     * The NPM package name when distributed via NPM, or null otherwise.
     */
    protected ?string $npmPackageName = null;

    /**
     * Lazily resolved binary manager bound to the working directory.
     *
     * @var \Laragear\Expose\Support\BinaryManager|null
     */
    protected ?BinaryManager $binaryManager = null;

    /**
     * @inheritDoc
     */
    public function isInstalled(): bool
    {
        return $this->manager()->isInstalled($this->binary());
    }

    /**
     * @inheritDoc
     */
    public function isInstallableViaNpm(): bool
    {
        return $this->npmPackage;
    }

    /**
     * @inheritDoc
     */
    public function npmPackageName(): ?string
    {
        return $this->npmPackageName;
    }

    /**
     * @inheritDoc
     */
    public function downloadUrl(): ?string
    {
        return $this->resolveDownloadUrl();
    }

    /**
     * Re-downloads the binary or reinstalls the NPM package. Subclasses may override.
     */
    public function update(SymfonyStyle $io, bool $force = false): void
    {
        if ($this->isInstallableViaNpm()) {
            $io->text("Updating <info>{$this->name()}</info> via NPM...");
            $this->manager()->installViaNpm((string) $this->npmPackageName());

            return;
        }

        if (!$url = $this->resolveDownloadUrl()) {
            throw new RuntimeException("No download URL defined for [{$this->name()}] on this platform.");
        }

        $io->text("Downloading latest <info>{$this->name()}</info> binary...");

        $this->manager()->downloadViaCurl($url, $this->binary());
    }

    /**
     * Removes the binary or NPM package from the system. Subclasses may override.
     */
    public function uninstall(SymfonyStyle $io): void
    {
        if ($this->isInstallableViaNpm() && $this->npmPackageName !== null) {
            $io->text("Uninstalling <info>{$this->name()}</info> NPM package...");
            $this->manager()->uninstallViaNpm($this->npmPackageName);

            return;
        }

        $io->text("Removing <info>{$this->name()}</info> binary...");
        $this->manager()->removeBinary($this->binary());
    }

    /**
     * No-op by default; subclasses override to write tokens or settings.
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function configurableOptions(): array
    {
        return [];
    }

    /**
     * Returns a not-running status by default; subclasses override to query a local API.
     *
     * @inheritDoc
     *
     * @return array{running: bool, url: ?string, connections: int|null, error?: ?string}
     */
    public function status(): array
    {
        return ['running' => false, 'url' => null, 'connections' => null, 'error' => null];
    }

    /**
     * Checks whether a process matching the given pattern is currently running.
     */
    protected function isProcessRunning(string $pattern): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            exec('tasklist /FO CSV /NH 2>NUL', $lines);

            foreach ($lines as $line) {
                if (stripos($line, $pattern) !== false) {
                    return true;
                }
            }

            return false;
        }

        exec('pgrep -f ' . escapeshellarg($pattern) . ' 2>/dev/null', $output, $exitCode);

        return $exitCode === 0;
    }

    /**
     * Returns the resolved invocation command for the binary (local copy preferred over PATH).
     */
    protected function binaryCommand(): string
    {
        return $this->manager()->resolveCommand($this->binary());
    }

    /**
     * Returns a lazily created BinaryManager bound to the current working directory.
     */
    protected function manager(): BinaryManager
    {
        return $this->binaryManager ??= new BinaryManager((string) getcwd());
    }

    /**
     * Creates a non-blocking-ready Process from a command array with no timeout.
     *
     * @param list<string> $command
     */
    protected function buildProcess(array $command): Process
    {
        return (new Process($command, (string) getcwd()))->setTimeout(null);
    }

    /**
     * Resolves the platform-specific binary download URL; returns null when unsupported.
     */
    protected function resolveDownloadUrl(): ?string
    {
        return null;
    }

    /**
     * Returns the binary name of the tunnel service.
     */
    public function binary(): string
    {
        return 'unknown binary';
    }

    /**
     * @inheritDoc
     */
    public function install(BinaryManager $manager, SymfonyStyle $io): bool
    {
        $io->warning("{$this->name()} binary (`{$this->binary()}`) is not installed.");

        if (!$url = $this->downloadUrl()) {
            $io->error("No download URL available for your platform. Please install {$this->name()} manually.");

            return false;
        }

        if (!$io->confirm("Download and install {$this->name()} automatically?")) {
            $io->text("Download it from: <comment>$url</comment>");

            return false;
        }

        $io->text("Downloading <info>{$this->name()}</info>...");
        $manager->downloadViaCurl($url, $this->binary());
        $io->success("{$this->name()} installed.");

        return true;
    }
}
