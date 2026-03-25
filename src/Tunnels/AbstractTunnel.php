<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Http;
use Laragear\Expose\Support\PHP;
use Laragear\Expose\Support\ProcessFactory;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use function array_shift;
use function preg_match;

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
     * Create a new Abstract Tunnel instance.
     */
    public function __construct(
        protected Http $http,
        protected BinaryManager $binaryManager,
        protected ProcessFactory $processFactory,
    ) {
        //
    }

    /**
     * @inheritDoc
     */
    public function isInstalled(): bool
    {
        return $this->binaryManager->isInstalled($this->binary());
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
    public function update(BinaryManager $manager, SymfonyStyle $io, bool $force = false): void
    {
        if ($this->isInstallableViaNpm()) {
            $io->text("Updating <info>{$this->name()}</info> via NPM...");
            $manager->installViaNpm((string) $this->npmPackageName());

            return;
        }

        if (!$url = $this->resolveDownloadUrl()) {
            throw new RuntimeException("No download URL defined for [{$this->name()}] on this platform.");
        }

        $io->text("Downloading latest <info>{$this->name()}</info> binary...");

        $manager->downloadViaCurl($url, $this->binary());
    }

    /**
     * Removes the binary or NPM package from the system. Subclasses may override.
     */
    public function uninstall(BinaryManager $manager, SymfonyStyle $io): void
    {
        if ($this->isInstallableViaNpm() && $this->npmPackageName !== null) {
            $io->text("Uninstalling <info>{$this->name()}</info> NPM package...");

            $manager->uninstallViaNpm($this->npmPackageName);
        } else {
            $io->text("Removing <info>{$this->name()}</info> binary...");

            $manager->removeBinary($this->binary());
        }
    }

    /**
     * No-op by default; subclasses override to write tokens or settings.
     *
     * @inheritDoc
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
     * @inheritDoc
     */
    public function publishedAddress(Process $tunnelProcess): ?string
    {
        $output = $tunnelProcess->getOutput().$tunnelProcess->getErrorOutput();

        preg_match(
            '#https?://[^\s"\'<>]+\.(?:ngrok|trycloudflare|loca\.lt|pinggy|zrok)\.[a-z]+[^\s"\'<>]*#i', $output, $m,
        );

        return $m[0] ?? null;
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
        if ($this->processFactory->isWindows()) {
            $execution = $this->processFactory->call('tasklist /FO CSV /NH 2>NUL');

            foreach ($execution as $line) {
                if (stripos($line, $pattern) !== false) {
                    return true;
                }
            }

            return false;
        }

        return $this->processFactory->command('pgrep', '-f')->args($pattern)->muteErrors()->isSuccessful();
    }

    /**
     * Returns the resolved invocation command for the binary (local copy preferred over PATH).
     */
    protected function binaryCommand(): string
    {
        return $this->binaryManager->resolveCommand($this->binary());
    }

    /**
     * Creates a non-blocking-ready Process from a command array with no timeout.
     */
    protected function buildProcess(string $command, string ...$rawArgs): ProcessFactory
    {
        return $this->processFactory->command($command, ...$rawArgs)->setTimeout(null); // @phpstan-ignore-line
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
        throw new RuntimeException('No binary name was provided.');
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
