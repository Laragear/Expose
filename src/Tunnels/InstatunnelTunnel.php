<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Tunnel implementation for InsTunnel (instatunnel.com).
 */
class InstatunnelTunnel extends AbstractTunnel
{
    /**
     * @inheritDoc
     */
    protected bool $npmPackage = true;

    /**
     * @inheritDoc
     */
    protected ?string $npmPackageName = 'instatunnel';

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'InsTunnel';
    }

    /**
     * @inheritDoc
     */
    public function binary(): string
    {
        return 'instatunnel';
    }

    /**
     * @inheritDoc
     */
    public function configurableOptions(): array
    {
        return [
            'token'     => ['label' => 'InsTunnel API Token', 'default' => null, 'secret' => true],
            'subdomain' => ['label' => 'Preferred subdomain (leave blank for random)', 'default' => null, 'secret' => false],
        ];
    }

    /**
     * Writes the auth token to the InsTunnel configuration file.
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        if (! empty($values['token'])) {
            $process = $this->buildProcess([$this->binaryCommand(), 'auth', $values['token']]);
            $process->run();

            $io->success('InsTunnel token saved.');
        }
    }

    /**
     * @inheritDoc
     */
    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        $command = [$this->binaryCommand(), '--port', (string) $port, '--host', $host];

        $process = $this->buildProcess($command);
        $process->start();

        return $process;
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'InsTunnel (instatunnel.com)';
    }
}
