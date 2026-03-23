<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Tunnel implementation for ngrok (ngrok.com).
 */
class NgrokTunnel extends AbstractTunnel
{
    /**
     * The ngrok local API port used to query tunnel status.
     */
    protected const int API_PORT = 4040;

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'ngrok';
    }

    /**
     * @inheritDoc
     */
    public function binary(): string
    {
        return 'ngrok';
    }

    /**
     * @inheritDoc
     */
    public function configurableOptions(): array
    {
        return [
            'authtoken' => ['label' => 'ngrok Auth Token', 'default' => null, 'secret' => true],
            'hostname'  => ['label' => 'Custom hostname (leave blank for random)', 'default' => null, 'secret' => false],
            'region'    => ['label' => 'Region (us/eu/au/ap/sa/jp/in)', 'default' => 'us', 'secret' => false],
        ];
    }

    /**
     * Saves the auth token by running `ngrok config add-authtoken`.
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        if (! empty($values['authtoken'])) {
            $process = $this->buildProcess($this->binaryCommand(), 'config', 'add-authtoken')
                ->args($values['authtoken'])
                ->process();

            $process->run();

            $io->success('ngrok auth token saved.');
        }
    }

    /**
     * @inheritDoc
     */
    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        $process = $this->buildProcess($this->binaryCommand(), 'http', '--log', 'stdout', "$host:$port")->process();

        $process->start();

        return $process;
    }

    /**
     * @inheritDoc
     */
    public function status(): array
    {
        $context = stream_context_create(['http' => ['timeout' => 2]]);
        $raw = @file_get_contents('http://localhost:' . self::API_PORT . '/api/tunnels', false, $context);

        if ($raw === false) {
            return ['running' => false, 'url' => null, 'connections' => null];
        }

        $data = json_decode($raw, true);

        $url = $data['tunnels'][0]['public_url'] ?? null;

        return [
            'running'     => $url !== null,
            'url'         => $url,
            'connections' => $data['tunnels'][0]['metrics']['conns']['count'] ?? null,
        ];
    }

    /**
     * Updates ngrok by running `ngrok update`.
     */
    public function update(SymfonyStyle $io, bool $force = false): void
    {
        $io->text('Updating <info>ngrok</info>...');

        $this->buildProcess($this->binaryCommand(), 'update')->run();

        $io->success('ngrok updated.');
    }

    /**
     * @inheritDoc
     */
    protected function resolveDownloadUrl(): ?string
    {
        return match (PHP_OS_FAMILY) {
            'Linux'   => 'https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-amd64.tgz',
            'Darwin'  => 'https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-darwin-amd64.zip',
            'Windows' => 'https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-amd64.zip',
            default   => null,
        };
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'ngrok (ngrok.com)';
    }
}
