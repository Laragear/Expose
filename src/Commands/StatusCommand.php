<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Commands\Concerns\ResolvesTunnel;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Support\ComposerConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use const DIRECTORY_SEPARATOR;

/**
 * Displays the current status of the configured tunnel service.
 */
class StatusCommand extends BaseCommand
{
    use ResolvesTunnel;

    /**
     * Configures the command name, description, and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('expose:status')
            ->setDescription('Show the current status of the configured tunnel service.')
            ->addOption('tunnel', 't', InputOption::VALUE_OPTIONAL, 'Override which tunnel to check.');
    }

    /**
     * Resolves the tunnel, queries its status, and renders the result as a definition list.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = app(SymfonyStyle::class);

        $key = $this->requireSavedTunnelKey(app(ComposerConfig::class), $input->getOption('tunnel'));

        $tunnel = $this->registry()->make($key);

        $io->title("{$tunnel->name()} Status");

        if (!$tunnel instanceof InstallableTunnel) {
            $io->warning("{$tunnel->name()} has no logic for installation.");

            return self::SUCCESS;
        }

        if (!$tunnel->isInstalled()) {
            $io->warning("{$tunnel->name()} is not installed.");

            return self::SUCCESS;
        }

        $status = $tunnel->status();

        $io->definitionList(
            ['Status' => $status['running'] ? '<info>Running</info>' : '<comment>Not running</comment>'],
            ['Public URL' => $status['url'] ?? '-'],
            ['Connections' => $status['connections'] !== null ? (string) $status['connections'] : '-'],
        );

        return self::SUCCESS;
    }
}
