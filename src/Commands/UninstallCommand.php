<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\ComposerConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the tunnel service binary and optionally clears its configuration from composer.json.
 */
class UninstallCommand extends BaseCommand
{
    use Concerns\ResolvesServices;

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
     * Confirms with the user and delegates to the tunnel's uninstallation routine.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $this->requireSavedTunnelKey($input->getOption('tunnel'));

        $tunnel = $this->registry()->make($key);

        if (!$tunnel instanceof InstallableTunnel) {
            $this->io()->error("{$tunnel->name()} is not uninstallable. You have to remove it manually.");

            return self::FAILURE;
        }

        if (!$tunnel->isInstalled()) {
            $this->io()->note("{$tunnel->name()} does not appear to be installed.");

            return self::FAILURE;
        }

        if (!$this->io()->confirm("Are you sure you want to uninstall {$tunnel->name()}?", false)) {
            $this->io()->error('Uninstall cancelled.');

            return self::FAILURE;
        }

        $this->io()->title("Uninstalling {$tunnel->name()}...");
        $tunnel->uninstall(app(BinaryManager::class), $this->io());

        if ($input->getOption('purge')) {
            $this->purgeComposerConfig();
        }

        $this->io()->success("{$tunnel->name()} has been uninstalled.");

        return self::SUCCESS;
    }

    /**
     * Removes the entire `extra.expose` block from composer.json.
     */
    protected function purgeComposerConfig(): void
    {
        foreach (array_keys($this->config()->all()) as $key) {
            $this->config()->forget($key);
        }

        $this->io()->text('Expose configuration removed from <comment>composer.json</comment>.');
    }
}
