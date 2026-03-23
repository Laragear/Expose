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
 * Updates the tunnel service binary to the latest available version.
 */
class UpdateCommand extends BaseCommand
{
    use ResolvesTunnel;

    /**
     * Configures the command name, description, and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('expose:update')
            ->setDescription('Update the configured tunnel service binary.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force re-download even if already up to date.')
            ->addOption('tunnel', 't', InputOption::VALUE_OPTIONAL, 'Override which tunnel to update.');
    }

    /**
     * Resolves the tunnel and delegates to its update routine.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = app(SymfonyStyle::class);
        $config = app(ComposerConfig::class);
        $force = (bool) $input->getOption('force');

        $key = $this->requireSavedTunnelKey($config, $input->getOption('tunnel'));
        $tunnel = $this->registry()->make($key);

        if (!$tunnel instanceof InstallableTunnel) {
            $io->error("{$tunnel->name()} is not installable. You have to install it manually.");

            return self::FAILURE;
        }

        if (!$tunnel->isInstalled()) {
            $io->error("{$tunnel->name()} does not appear to be installed. Run `composer expose` first.");

            return self::FAILURE;
        }

        $io->title("Updating {$tunnel->name()}...");
        $tunnel->update($io, $force);
        $io->success("{$tunnel->name()} is up to date.");

        return self::SUCCESS;
    }
}
