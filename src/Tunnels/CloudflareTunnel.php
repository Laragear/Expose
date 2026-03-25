<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Laragear\Expose\Support\Option;
use Laragear\Expose\Support\ProcessFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use function json_decode;

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
            'token' => Option::secret('Cloudflare Tunnel Token (optional for quick tunnels)')->optional(),
            'hostname' => Option::name('Custom hostname (optional)')->optional(),
        ];
    }

    /**
     * Installs the tunnel service token via `cloudflared service install`.
     *
     * @inheritDoc
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        if (!empty($values['token'])) {
            $this->buildProcess($this->binaryCommand(), 'service', 'install')->args($values['token'])->run();

            $io->success('Cloudflare Tunnel token installed.');
        }
    }

    /**
     * @inheritDoc
     */
    public function start(ProcessFactory $factory, string $host = 'localhost', int $port = 8080): Process
    {
        $process = $factory->command(
            $this->binaryCommand(),
            'tunnel',
            '--url', "http://$host:$port",
            '--metrics', 'localhost:'.self::METRICS_PORT,
            '--no-autoupdate',
        )
            ->setTimeout(null)
            ->process(); // @phpstan-ignore-line

        $process->start();

        return $process;
    }

    /**
     * @inheritDoc
     */
    protected function resolveDownloadUrl(): ?string
    {
        $arch = $this->processFactory->arch() === 'arm64' ? 'arm64' : 'amd64';

        return match ($this->processFactory->os()) {
            'Linux' => "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-$arch",
            'Darwin' => "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-darwin-$arch.tgz",
            'Windows' => "https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-$arch.exe",
            default => null,
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
        $raw = $this->http->localGet(self::METRICS_PORT, 'ready');

        if ($raw === false || !$data = json_decode($raw, true)) {
            return ['running' => false, 'url' => null, 'connections' => null];
        }

        $running = ($data['status'] ?? '') === 'ok';

        return ['running' => $running, 'url' => null, 'connections' => null];
    }
}
