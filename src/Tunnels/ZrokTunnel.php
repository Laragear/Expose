<?php

declare(strict_types=1);

namespace Laragear\Expose\Tunnels;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/** Tunnel implementation for Zrok (zrok.io). */
class ZrokTunnel extends AbstractTunnel
{
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
            "http://{$host}:{$port}",
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
    public function label(): string
    {
        return 'Zrok (zrok.io)';
    }
}
