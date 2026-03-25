<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Option;
use Laragear\Expose\Support\ProcessFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use function json_decode;

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
            'authtoken' => Option::secret('ngrok Auth Token'),
            'hostname' => Option::name('Custom hostname (leave blank for random)'),
            'region' => Option::name('Region (us/eu/au/ap/sa/jp/in)', 'us'),
        ];
    }

    /**
     * Saves the auth token by running `ngrok config add-authtoken`.
     */
    public function configure(SymfonyStyle $io, array $values): void
    {
        if (!empty($values['authtoken'])) {
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
    public function start(ProcessFactory $factory, string $host = 'localhost', int $port = 8080): Process
    {
        $process = $factory
            ->command($this->binaryCommand(), 'http', '--log', 'stdout', "$host:$port")
            ->setTimeout(null)
            ->process(); // @phpstan-ignore-line

        $process->start();

        return $process;
    }

    /**
     * @inheritDoc
     */
    public function status(): array
    {
        $raw = $this->http->localGet(self::API_PORT, 'api/tunnels', false);

        if ($raw === false || !$data = json_decode($raw, true)) {
            return ['running' => false, 'url' => null, 'connections' => null];
        }

        $url = $data['tunnels'][0]['public_url'] ?? null;

        return [
            'running' => $url !== null,
            'url' => $url,
            'connections' => $data['tunnels'][0]['metrics']['conns']['count'] ?? null,
        ];
    }

    /**
     * Updates ngrok by running `ngrok update`.
     */
    public function update(BinaryManager $manager, SymfonyStyle $io, bool $force = false): void
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
        $arch = $this->processFactory->arch() === 'arm64' ? 'arm64' : 'amd64';

        return match ($this->processFactory->os()) {
            'Linux' => "https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-$arch.tgz",
            'Darwin' => "https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-darwin-$arch.zip",
            'Windows' => "https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-windows-$arch.zip",
            default => null,
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
