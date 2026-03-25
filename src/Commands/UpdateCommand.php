<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Support\BinaryManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Updates the tunnel service binary to the latest available version.
 */
class UpdateCommand extends BaseCommand
{
    use Concerns\ResolvesServices;

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
        $key = $this->requireSavedTunnelKey($input->getOption('tunnel'));

        $tunnel = $this->registry()->make($key);

        if (!$tunnel instanceof InstallableTunnel) {
            $this->io()->error("{$tunnel->name()} is not updateable. You have to update it manually.");

            return self::FAILURE;
        }

        if (!$tunnel->isInstalled()) {
            $this->io()->note("{$tunnel->name()} does not appear to be installed.");

            return self::FAILURE;
        }

        $this->io()->title("Updating {$tunnel->name()}...");
        $tunnel->update(app(BinaryManager::class), $this->io(), (bool) $input->getOption('force'));
        $this->io()->success("{$tunnel->name()} is up to date.");

        return self::SUCCESS;
    }
}
