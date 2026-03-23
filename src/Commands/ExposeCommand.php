<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Commands\Concerns\ResolvesTunnel;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Detectors\ProjectDetector;
use Laragear\Expose\Enums\Framework;
use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Support\ServerRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use function app;
use const DIRECTORY_SEPARATOR;

/**
 * Exposes the local project to the internet using the configured tunnel service.
 */
class ExposeCommand extends BaseCommand
{
    use ResolvesTunnel;

    /**
     * The default host to bind the local server to.
     */
    protected const string DEFAULT_HOST = 'localhost';

    /**
     * The default port to bind the local server to.
     */
    protected const int DEFAULT_PORT = 8080;

    /**
     * Configures the command name, description, and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('expose')
            ->setDescription('Expose your local PHP project to the internet via a tunnel service.')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The local host to serve from.', self::DEFAULT_HOST)
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The local port to serve from.', self::DEFAULT_PORT)
            ->addOption('tunnel', 't', InputOption::VALUE_OPTIONAL, 'Override the tunnel service for this run.');
    }

    /**
     * Runs the full expose workflow: detect -> configure -> start server -> start tunnel.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = app(SymfonyStyle::class);
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');
        $config = app(ComposerConfig::class);
        $framework = $this->detectFramework($io);
        $tunnel = $this->resolveTunnel($io, $config, $input->getOption('tunnel'));

        if (!$this->checkBinaryInstalled($io, $tunnel, app(BinaryManager::class))) {
            return self::FAILURE;
        }

        $io->section("Starting {$framework->label()} project on http://$host:$port");

        $serverProcess = $this->startServer($io, $framework, $host, $port);
        $tunnelProcess = $this->startTunnel($io, $tunnel, $host, $port);

        $this->waitForShutdown($io, $serverProcess, $tunnelProcess);

        return self::SUCCESS;
    }

    /**
     * Detects the framework and prints the result to the console.
     */
    protected function detectFramework(SymfonyStyle $io): Framework
    {
        $framework = app(ProjectDetector::class)->detect();

        $io->text("Detected project: <info>{$framework->label()}</info>");

        return $framework;
    }

    /**
     * Verifies the tunnel binary is installed, guiding the user if it is not.
     */
    protected function checkBinaryInstalled(SymfonyStyle $io, Tunnel $tunnel, BinaryManager $manager): bool
    {
        if (!$tunnel instanceof InstallableTunnel) {
            $io->info("Tunnel [{$tunnel->name()}] may not be installed.");

            return true;
        }

        if ($tunnel->isInstalled()) {
            return true;
        }

        return $tunnel->isInstallableViaNpm()
            ? $this->handleMissingNpmBinary($io, $tunnel, $manager)
            : $this->handleMissingDownloadBinary($io, $tunnel, $manager);
    }

    /**
     * Handles the case where an NPM-based tunnel binary is missing.
     */
    protected function handleMissingNpmBinary(SymfonyStyle $io, InstallableTunnel $tunnel, BinaryManager $manager): bool
    {
        $package = $tunnel->npmPackageName();

        $io->warning("{$tunnel->name()} is not installed. It is available as the NPM package `{$package}`.");

        if (!$manager->isNpmAvailable()) {
            $io->error('NPM is not installed. Please install Node.js and NPM first, then run: npm install -g '.$package);

            return false;
        }

        $choice = $io->choice(
            'What would you like to do?',
            [
                'install' => "Install `$package` via NPM now",
                'manual' => 'I will install it manually and retry',
            ],
        );

        if ($choice === 'manual') {
            $io->text("Run this command, then try again: <comment>npm install -g $package</comment>");

            return false;
        }

        $manager->installViaNpm((string) $package);

        $io->success("{$tunnel->name()} installed.");

        return true;
    }

    /**
     * Handles the case where a cURL-downloaded tunnel binary is missing.
     */
    protected function handleMissingDownloadBinary(
        SymfonyStyle $io,
        InstallableTunnel $tunnel,
        BinaryManager $manager,
    ): bool {
        return $tunnel->install($manager, $io);
    }

    /**
     * Starts the local PHP development server.
     */
    protected function startServer(SymfonyStyle $io, Framework $framework, string $host, int $port): Process
    {
        $process = app(ServerRunner::class)->start($framework, $host, $port);

        $io->text('Local server started. Waiting for tunnel URL...');

        return $process;
    }

    /**
     * Starts the tunnel and prints the public URL once available.
     */
    protected function startTunnel(SymfonyStyle $io, Tunnel $tunnel, string $host, int $port): Process
    {
        $io->text("Starting <info>{$tunnel->name()}</info> tunnel...");

        $process = $tunnel->start($host, $port);

        $this->printTunnelUrl($io, $process);

        return $process;
    }

    /**
     * Polls the tunnel output until a public URL is detected, then prints it.
     */
    protected function printTunnelUrl(SymfonyStyle $io, Process $process): void
    {
        $url = $this->pollForUrl($process);

        $url !== null
            ? $io->success("Public URL: {$url}")
            : $io->note('Could not auto-detect the public URL. Check tunnel output above.');
    }

    /**
     * Reads tunnel process output and attempts to extract a public HTTPS URL.
     */
    protected function pollForUrl(Process $process): ?string
    {
        $deadline = time() + 15;

        while (time() < $deadline && $process->isRunning()) {
            $output = $process->getOutput().$process->getErrorOutput();

            if (preg_match(
                '#https?://[^\s"\'<>]+\.(?:ngrok|trycloudflare|loca\.lt|pinggy|zrok)\.[a-z]+[^\s"\'<>]*#i',
                $output,
                $m,
            )) {
                return $m[0];
            }

            // @codeCoverageIgnoreStart
            usleep(500_000);
            // @codeCoverageIgnoreEnd
        }

        return null;
    }

    /**
     * Blocks until the user presses Ctrl+C or a process stops, then shuts everything down.
     *
     * @codeCoverageIgnore
     */
    protected function waitForShutdown(SymfonyStyle $io, Process $serverProcess, Process $tunnelProcess): void
    {
        $io->text('<comment>Press Ctrl+C to stop the tunnel and server.</comment>');

        while ($serverProcess->isRunning() && $tunnelProcess->isRunning()) {
            usleep(500_000);
        }

        $tunnelProcess->stop();
        $serverProcess->stop();

        $io->newLine();
        $io->success('Tunnel and server stopped.');
    }
}
