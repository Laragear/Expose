<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Detectors\ProjectDetector;
use Laragear\Expose\Enums\Framework;
use Laragear\Expose\NpmInstaller\BinaryInstaller;
use Laragear\Expose\NpmInstaller\NpmInstaller;
use Laragear\Expose\Support\Date;
use Laragear\Expose\Support\ServerRunner;
use Laragear\Expose\Support\TunnelRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use function app;

/**
 * Exposes the local project to the internet using the configured tunnel service.
 */
class ExposeCommand extends BaseCommand
{
    use Concerns\ResolvesServices;

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
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');
        $framework = $this->detectFramework();

        $tunnel = $this->resolveTunnel($input->getOption('tunnel'));

        if (!$this->checkBinaryInstalled($tunnel)) {
            return self::FAILURE;
        }

        $this->io()->section("Starting {$framework->label()} project on http://$host:$port");

        $serverProcess = $this->startServer($framework, $host, $port);
        $tunnelProcess = $this->startTunnel($tunnel, $host, $port);

        $this->waitForShutdown($serverProcess, $tunnelProcess);

        return self::SUCCESS;
    }

    /**
     * Detects the framework and prints the result to the console.
     */
    protected function detectFramework(): Framework
    {
        $framework = app(ProjectDetector::class)->detect();

        $this->io()->text("Detected project: <info>{$framework->label()}</info>");

        return $framework;
    }

    /**
     * Verifies the tunnel binary is installed, guiding the user if it is not.
     */
    protected function checkBinaryInstalled(Tunnel $tunnel): bool
    {
        if (!$tunnel instanceof InstallableTunnel) {
            $this->io()->info("Tunnel [{$tunnel->name()}] may not be installed.");

            return true;
        }

        if ($tunnel->isInstalled()) {
            return true;
        }

        return $tunnel->isInstallableViaNpm()
            ? app(NpmInstaller::class)->install($tunnel)
            : app(BinaryInstaller::class)->install($tunnel);
    }

    /**
     * Starts the local PHP development server.
     */
    protected function startServer(Framework $framework, string $host, int $port): Process
    {
        $process = app(ServerRunner::class)->start($framework, $host, $port);

        $this->io()->text('Local server started. Waiting for tunnel...');

        return $process;
    }

    /**
     * Starts the tunnel and prints the public URL once available.
     */
    protected function startTunnel(Tunnel $tunnel, string $host, int $port): Process
    {
        $process = app(TunnelRunner::class)->start($tunnel, $host, $port);

        $this->io()->text("Started <info>{$tunnel->name()}</info> tunnel.");

        return $process;
    }

    /**
     * Blocks until the user presses Ctrl+C or a process stops, then shuts everything down.
     *
     * @codeCoverageIgnore
     */
    protected function waitForShutdown(Process $serverProcess, Process $tunnelProcess): void
    {
        $this->io()->text('<comment>Press Ctrl+C to stop the tunnel and server.</comment>');

        $date = app(Date::class);

        while ($serverProcess->isRunning() && $tunnelProcess->isRunning()) {
            $date->sleep();
        }

        $tunnelProcess->stop();
        $serverProcess->stop();

        $this->io()->newLine();
        $this->io()->success('Tunnel and server stopped.');
    }
}
