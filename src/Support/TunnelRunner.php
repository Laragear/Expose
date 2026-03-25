<?php

namespace Laragear\Expose\Support;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\Date;
use Laragear\Expose\Support\ProcessFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use function app;
use function preg_match;

/**
 * Starts a Tunnel binary, trying to find the target URL.
 */
class TunnelRunner
{
    /**
     * Create a new Tunnel Starter instance.
     */
    public function __construct(protected SymfonyStyle $io, protected ProcessFactory $factory, protected Date $date)
    {
        //
    }

    /**
     * Starts the tunnel.
     */
    public function start(Tunnel $tunnel, string $host, int $port): Process
    {
        $this->io->text("Starting <info>{$tunnel->name()}</info> tunnel...");

        $process = $tunnel->start($this->factory, $host, $port);

        $this->printTunnelUrl($process, $tunnel);

        return $process;
    }

    /**
     * Polls the tunnel output until a public URL is detected, then prints it.
     */
    protected function printTunnelUrl(Process $process, Tunnel $tunnel): void
    {
        $url = $this->pollForUrl($process, $tunnel);

        $url !== null
            ? $this->io->success("Public URL: $url")
            : $this->io->note('Could not auto-detect the public URL in 15 seconds. Check tunnel output above.');
    }

    /**
     * Reads tunnel process output and attempts to extract a public HTTPS URL.
     */
    protected function pollForUrl(Process $tunnelProcess, Tunnel $tunnel): ?string
    {
        $deadline = $this->date->now(15);

        while ($this->date->now() < $deadline && $tunnelProcess->isRunning()) {
            if ($address = $tunnel->publishedAddress($tunnelProcess)) {
                return $address;
            }

            $this->date->sleep();
        }

        return null;
    }
}
