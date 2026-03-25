<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
/**
 * Displays the current status of the configured tunnel service.
 */
class StatusCommand extends BaseCommand
{
    use Concerns\ResolvesServices;

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
        $key = $this->requireSavedTunnelKey($input->getOption('tunnel'));

        $tunnel = $this->registry()->make($key);

        $this->io()->title("{$tunnel->name()} Status");

        if (!$tunnel instanceof InstallableTunnel) {
            $this->io()->error("{$tunnel->name()} has no logic for installation.");

            return self::FAILURE;
        }

        if (!$tunnel->isInstalled()) {
            $this->io()->error("{$tunnel->name()} is not installed.");

            return self::FAILURE;
        }

        $status = $tunnel->status();

        $this->io()->definitionList(
            ['Status' => $status['running'] ? '<info>Running</info>' : '<comment>Not running</comment>'],
            ['Public URL' => $status['url'] ?? '-'],
            ['Connections' => $status['connections'] !== null ? (string) $status['connections'] : '-'],
        );

        return self::SUCCESS;
    }
}
