<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Tunnel implementation for Localtunnel (localtunnel.me), distributed via NPM as `lt`.
 */
class LocaltunnelTunnel extends AbstractTunnel
{
    /**
     * @inheritDoc
     */
    protected bool $npmPackage = true;

    /**
     * @inheritDoc
     */
    protected ?string $npmPackageName = 'localtunnel';

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'Localtunnel';
    }

    /**
     * @inheritDoc
     */
    public function binary(): string
    {
        return 'lt';
    }

    /**
     * @inheritDoc
     */
    public function configurableOptions(): array
    {
        return [
            'subdomain' => ['label' => 'Preferred subdomain (leave blank for random)', 'default' => null, 'secret' => false],
        ];
    }

    /**
     * @inheritDoc
     */
    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        $command = [$this->binaryCommand(), '--port', (string) $port, '--local-host', $host];

        $process = $this->buildProcess($command);
        $process->start();

        return $process;
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'Localtunnel (localtunnel.me) [npm]';
    }
}
