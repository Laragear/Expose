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
 * Removes the tunnel service binary and optionally clears its configuration from composer.json.
 */
class UninstallCommand extends BaseCommand
{
    use ResolvesTunnel;

    /**
     * Configures the command name, description, and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('expose:uninstall')
            ->setDescription('Uninstall the tunnel service binary.')
            ->addOption('tunnel', 't', InputOption::VALUE_OPTIONAL, 'Override which tunnel to uninstall.')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Also remove all Expose config from composer.json.');
    }

    /**
     * Confirms with the user and delegates to the tunnel's uninstall routine.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = app(SymfonyStyle::class);
        $config = app(ComposerConfig::class);

        $key = $this->requireSavedTunnelKey($config, $input->getOption('tunnel'));
        $tunnel = $this->registry()->make($key);

        if (!$tunnel instanceof InstallableTunnel) {
            $io->error("{$tunnel->name()} is not uninstallable. You have to remove it manually.");

            return self::FAILURE;
        }

        if (!$tunnel->isInstalled()) {
            $io->note("{$tunnel->name()} does not appear to be installed.");

            return self::SUCCESS;
        }

        if (!$io->confirm("Are you sure you want to uninstall {$tunnel->name()}?", false)) {
            $io->text('Uninstall cancelled.');

            return self::SUCCESS;
        }

        $io->title("Uninstalling {$tunnel->name()}...");
        $tunnel->uninstall($io);

        if ($input->getOption('purge')) {
            $this->purgeComposerConfig($io, $config);
        }

        $io->success("{$tunnel->name()} has been uninstalled.");

        return self::SUCCESS;
    }

    /**
     * Removes the entire `extra.expose` block from composer.json.
     */
    protected function purgeComposerConfig(SymfonyStyle $io, ComposerConfig $config): void
    {
        foreach (array_keys($config->all()) as $key) {
            $config->forget($key);
        }

        $io->text('Expose configuration removed from <comment>composer.json</comment>.');
    }
}
