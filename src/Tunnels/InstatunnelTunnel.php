<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Laragear\Expose\Support\Option;
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
        return 'InstaTunnel';
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
            'token' => Option::secret('InsTunnel API Token'),
            'subdomain' => Option::name('Preferred subdomain (leave blank for random)'),
        ];
    }

    /**
     * Writes the auth token to the InsTunnel configuration file.
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        if (!empty($values['token'])) {
            $this->buildProcess($this->binaryCommand(), 'auth')->args($values['token'])->run();

            $io->success('InsTunnel token saved.');
        }
    }

    /**
     * @inheritDoc
     */
    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        $process = $this->buildProcess($this->binaryCommand(), '--port', (string) $port, '--host', $host)->process();

        $process->start();

        return $process;
    }

    /**
     * @inheritDoc
     */
    public function status(): array
    {
        // InstaTunnel does not expose a local HTTP status API, so process-level detection
        // is used as the fallback. On Unix/macOS `pgrep -f` matches the npm package name
        // in the Node.js invocation path (e.g. `.../node_modules/instatunnel/...`).
        // On Windows `tasklist` output is scanned for the same string.
        //
        // The public URL is only available from the binary's stdout at startup and
        // cannot be retrieved after the fact, so it is always returned as null here.
        return [
            'running' => $this->isProcessRunning($this->npmPackageName ?? $this->binary()),
            'url' => null,
            'connections' => null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'InsTunnel (instatunnel.com)';
    }
}
