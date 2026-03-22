<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Tunnel implementation for Cloudflare Tunnel via the `cloudflared` binary.
 */
class CloudflareTunnel extends AbstractTunnel
{
    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'Cloudflare Tunnel';
    }

    /**
     * The binary name to use to create the tunnel.
     */
    public function binary(): string
    {
        return 'cloudflared';
    }

    /**
     * @inheritDoc
     */
    public function configurableOptions(): array
    {
        return [
            'token'    => ['label' => 'Cloudflare Tunnel Token (optional for quick tunnels)', 'default' => null, 'secret' => true],
            'hostname' => ['label' => 'Custom hostname (optional)', 'default' => null, 'secret' => false],
        ];
    }

    /**
     * Installs the tunnel service token via `cloudflared service install`.
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        if (! empty($values['token'])) {
            $this->buildProcess([$this->binaryCommand(), 'service', 'install', $values['token']])->run();

            $io->success('Cloudflare Tunnel token installed.');
        }
    }

    /**
     * @inheritDoc
     */
    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        $process = $this->buildProcess([
            $this->binaryCommand(),
            'tunnel',
            '--url', "http://$host:$port",
            '--no-autoupdate',
        ]);

        $process->start();

        return $process;
    }

    /**
     * @inheritDoc
     */
    protected function resolveDownloadUrl(): ?string
    {
        $arch = php_uname('m') === 'arm64' ? 'arm64' : 'amd64';

        return match (PHP_OS_FAMILY) {
            'Linux'   => "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-$arch",
            'Darwin'  => "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-darwin-$arch.tgz",
            'Windows' => "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-$arch.exe",
            default   => null,
        };
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'Cloudflare Tunnel (cloudflared)';
    }
}
