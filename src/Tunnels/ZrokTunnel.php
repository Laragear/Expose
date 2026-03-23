<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Tunnel implementation for Zrok (zrok.io).
 */
class ZrokTunnel extends AbstractTunnel
{
    /**
     * The localhost port for the zrok share web console.
     */
    protected const int CONSOLE_PORT = 9191;

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'Zrok';
    }

    /**
     * @inheritDoc
     */
    public function binary(): string
    {
        return 'zrok';
    }

    /**
     * @inheritDoc
     */
    public function configurableOptions(): array
    {
        return [
            'token'      => ['label' => 'Zrok Enable Token', 'default' => null, 'secret' => true],
            'share_mode' => ['label' => 'Share mode (public/private)', 'default' => 'public', 'secret' => false],
        ];
    }

    /**
     * @inheritDoc
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        if (! empty($values['token'])) {
            $process = $this->buildProcess([$this->binaryCommand(), 'enable', $values['token']]);
            $process->run();

            $io->success('Zrok enabled with the provided token.');
        }
    }

    /**
     * @inheritDoc
     */
    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        $command = [
            $this->binaryCommand(),
            'share', 'public',
            '--backend-mode', 'proxy',
            '--bind-addr', '127.0.0.1:' . self::CONSOLE_PORT,
            "http://$host:$port",
        ];

        $process = $this->buildProcess($command);
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
            'Linux'   => "https://github.com/openziti/zrok/releases/latest/download/zrok_linux_{$arch}.tar.gz",
            'Darwin'  => "https://github.com/openziti/zrok/releases/latest/download/zrok_darwin_{$arch}.tar.gz",
            'Windows' => "https://github.com/openziti/zrok/releases/latest/download/zrok_windows_{$arch}.zip",
            default   => null,
        };
    }

    /**
     * @inheritDoc
     */
    public function status(): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout'       => 2,
                'ignore_errors' => true,
            ],
        ]);

        // When `zrok share public` runs it starts a local HTTP console server at
        // 127.0.0.1:CONSOLE_PORT. The /api/v1/overview endpoint returns a JSON
        // document that includes a "shares" array; each entry contains:
        //   - "token"            -> the unique share identifier
        //   - "frontendEndpoint" -> the public HTTPS URL (e.g. https://xxx.share.zrok.io)
        //   - "shareMode"        -> "public" or "private"
        //
        // If the console is unreachable (connection refused) or the response cannot
        // be decoded the tunnel is considered not running.
        $raw = @file_get_contents(
            'http://127.0.0.1:' . self::CONSOLE_PORT . '/api/v1/overview', false, $context,
        );

        if ($raw === false) {
            return ['running' => false, 'url' => null, 'connections' => null];
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return ['running' => false, 'url' => null, 'connections' => null];
        }

        // The overview contains a flat "shares" array at the top level; each
        // entry represents one active share in the current environment.
        $shares = $data['shares'] ?? [];

        if (empty($shares)) {
            return ['running' => false, 'url' => null, 'connections' => null];
        }

        $url = $shares[0]['frontendEndpoint'] ?? null;

        return [
            'running'     => true,
            'url'         => $url ?: null,
            'connections' => null, // connection count not available via console API
        ];
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'Zrok (zrok.io)';
    }
}
