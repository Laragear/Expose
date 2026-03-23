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
     * The localhost port pinned for the cloudflared Prometheus/readiness metrics server.
     */
    protected const int METRICS_PORT = 20241;

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
            '--metrics', 'localhost:' . self::METRICS_PORT,
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

    /**
     * Queries the cloudflared readiness endpoint to determine tunnel health.
     *
     * The cloudflared process exposes a "/ready" endpoint on its metrics server:
     *   HTTP 200 + {"status":"ok"}       -> tunnel is connected and proxying traffic.
     *   HTTP 503 + {"status":"starting"} -> cloudflared is running but not yet connected.
     *   Connection refused               -> cloudflared is not running at all.
     *
     * The public URL is printed by cloudflared to stderr at startup (for quick tunnels)
     * and is not available via the local metrics API, so it is always returned as null.
     *
     * @inheritDoc
     */
    public function status(): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout'       => 2,
                'ignore_errors' => true, // read body even on 4xx/5xx responses
            ],
        ]);

        $raw = @file_get_contents('http://127.0.0.1:' . self::METRICS_PORT . '/ready', false, $context);

        if ($raw === false) {
            return ['running' => false, 'url' => null, 'connections' => null];
        }

        $data    = json_decode($raw, true);
        $running = ($data['status'] ?? '') === 'ok';

        return ['running' => $running, 'url' => null, 'connections' => null];
    }
}
