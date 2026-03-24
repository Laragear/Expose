<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Laragear\Expose\Support\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
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
            'token' => Option::secret('Pinggy Access Token (optional)')->optional(),
            'subdomain' => Option::name('Preferred subdomain (optional)')->optional(),
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
        $process = $this->buildProcess(
            'ssh',
            '-p',
            (string) self::SSH_PORT,
            '-R', "0:$host:$port",
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'ServerAliveInterval=30',
            self::SSH_SERVER
        )->process();

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
    public function status(): array
    {
        // Pinggy is entirely SSH-based: it has no local HTTP API, no management port,
        // and creates no local listening socket (it uses SSH remote forwarding with -R).
        // The only reliable way to detect an active session is to find the ssh process
        // whose command line references the Pinggy server hostname.
        //
        // On Unix/macOS:  pgrep -f "a.pinggy.io" matches the ssh argument list.
        // On Windows:     ssh.exe arguments are not visible via tasklist, so we fall back
        //                 to checking for any running ssh.exe process as a best-effort
        //                 proxy - the user is unlikely to have other simultaneous SSH
        //                 sessions in a typical developer workflow.
        //
        // The public URL assigned by Pinggy (e.g. https://xxxxx.a.free.pinggy.link) is
        // printed to stdout at session startup and is not recoverable afterwards, so it
        // is always returned as null here.
        if ($this->processFactory->isWindows()) {
            // On Windows, tasklist does not expose process arguments, so we can only
            // check for any ssh.exe process as a best-effort indicator.
            $running = $this->isProcessRunning('ssh.exe');
        } else {
            // Match on the Pinggy hostname appearing in the ssh command-line arguments.
            $running = $this->isProcessRunning(self::SSH_SERVER);
        }

        return ['running' => $running, 'url' => null, 'connections' => null];
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'Pinggy (pinggy.io) [ssh-based]';
    }
}
