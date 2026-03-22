<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Tunnel implementation for Pinggy (pinggy.io), which tunnels via SSH.
 */
class PinggyTunnel extends AbstractTunnel
{
    /**
     * The Pinggy remote SSH server.
     */
    protected const string SSH_SERVER = 'a.pinggy.io';

    /**
     * The SSH port used by Pinggy.
     */
    protected const int SSH_PORT = 443;

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'Pinggy';
    }

    /**
     * @inheritDoc
     */
    public function binary(): string
    {
        return 'ssh';
    }

    /**
     * Pinggy uses the system SSH binary, so checking install means checking for `ssh`.
     */
    public function isInstalled(): bool
    {
        return (new ExecutableFinder())->find('ssh') !== null;
    }

    /**
     * Pinggy is not distributed via NPM.
     */
    public function isInstallableViaNpm(): bool
    {
        return false;
    }

    /**
     * Pinggy has no NPM package.
     */
    public function npmPackageName(): ?string
    {
        return null;
    }

    /**
     * Pinggy requires no binary download.
     */
    public function downloadUrl(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function configurableOptions(): array
    {
        return [
            'token' => ['label' => 'Pinggy Access Token (optional)', 'default' => null, 'secret' => true],
            'subdomain' => ['label' => 'Preferred subdomain (optional)', 'default' => null, 'secret' => false],
        ];
    }

    /**
     * Pinggy uses SSH, so there is no binary configuration step.
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        $io->note('Pinggy credentials are passed at runtime via SSH options. Nothing stored locally.');
    }

    /**
     * @inheritDoc
     */
    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        $command = [
            'ssh',
            '-p', (string) self::SSH_PORT,
            '-R', "0:{$host}:{$port}",
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'ServerAliveInterval=30',
            self::SSH_SERVER,
        ];

        $process = $this->buildProcess($command);
        $process->start();

        return $process;
    }

    /**
     * Pinggy is SSH-based; there is nothing to update or uninstall.
     */
    public function update(SymfonyStyle $io, bool $force = false): void
    {
        $io->note('Pinggy is SSH-based. Ensure your system SSH client is up to date.');
    }

    /**
     * @inheritDoc
     */
    public function uninstall(SymfonyStyle $io): void
    {
        $io->note('Pinggy is SSH-based and has no binary to remove.');
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'Pinggy (pinggy.io) [ssh-based]';
    }
}
