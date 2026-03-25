<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Enums\TunnelService;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\TunnelRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function app;
use function method_exists;
use const DIRECTORY_SEPARATOR;

/**
 * Lists every tunnel service available in the current project, including any
 * custom services registered via composer.json or installed packages.
 */
class ListTunnelsCommand extends BaseCommand
{
    use Concerns\ResolvesServices;

    /**
     * Configures the command name, description, and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('expose:list')
            ->setDescription('List all available tunnel services, including custom and package-provided ones.')
            ->addOption(
                'installed', 'i', InputOption::VALUE_NONE, 'Show only services whose binary is currently installed.'
            );
    }

    /**
     * Builds the registry, renders every service as a table row, and marks the active one.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $activeTunnel = $this->config()->get('tunnel');

        $this->io()->title('Available Tunnel Services');

        $rows = $this->buildRows($activeTunnel, (bool) $input->getOption('installed'));

        if (empty($rows)) {
            $this->io()->note('No tunnel services found. Try removing the --installed filter.');

            return self::SUCCESS;
        }

        $this->io()->table(['Key', 'Label', 'Type', 'Binary', 'Installed', 'Active'], $rows);

        $this->printHelp($activeTunnel);

        return self::SUCCESS;
    }

    /**
     * Builds the table rows, one per registered tunnel key.
     *
     * @return list<list<string>>
     */
    protected function buildRows(mixed $activeTunnel, bool $onlyInstalled): array
    {
        $rows = [];

        $registry = app(TunnelRegistry::class);

        foreach ($registry->keys() as $key) {
            $tunnel = $registry->make($key);

            $installed = $tunnel instanceof InstallableTunnel && $tunnel->isInstalled();

            if ($onlyInstalled && !$installed) {
                continue;
            }

            $rows[] = [
                $key,
                $tunnel->name(),
                $this->typeLabel($key),
                method_exists($tunnel, 'binary') ? $tunnel->binary() : 'unknown binary',
                $installed ? '<info>yes</info>' : '<comment>no</comment>',
                $key === $activeTunnel ? '<info>YES</info>' : '',
            ];
        }

        return $rows;
    }

    /**
     * Returns a human-readable type label for a given key.
     */
    protected function typeLabel(string $key): string
    {
        return TunnelService::tryFrom($key) !== null ? 'built-in' : '<fg=cyan>custom</>';
    }

    /**
     * Prints contextual help based on whether a tunnel is already configured.
     */
    protected function printHelp(mixed $activeTunnel): void
    {
        $this->io()->note(
            $activeTunnel
                ? "Active tunnel: <info>$activeTunnel</info>. Change it with: composer expose:configure --reset"
                : 'No tunnel configured yet. Run `composer expose` to choose one.'
        );
    }
}
