<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

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
        $process = $this->buildProcess(
            $this->binaryCommand(), '--port', (string) $port, '--local-host', $host
        )->process();

        $process->start();

        return $process;
    }

    /**
     * @inheritDoc
     */
    public function status(): array
    {
        // Localtunnel does not expose a local HTTP status API. The binary `lt` is a
        // thin NPM wrapper around Node.js, so the npm package name ("localtunnel")
        // always appears in the Node.js process path (e.g. `.../localtunnel/bin/lt.js`).
        // Matching on that string is more reliable than matching the short alias "lt",
        // which is too generic and could collide with unrelated processes.
        //
        // The public URL (e.g. https://xxx.loca.lt) is printed to stdout only at
        // startup and is not retrievable afterwards, so it is always returned as null.
        return [
            'running'     => $this->isProcessRunning($this->npmPackageName ?? $this->binary()),
            'url'         => null,
            'connections' => null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'Localtunnel (localtunnel.me) [npm]';
    }
}
