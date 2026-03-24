<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Commands\Concerns\ResolvesTunnel;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_map;
use const DIRECTORY_SEPARATOR;

/**
 * Interactively configures the tunnel service credentials and common options.
 */
class ConfigureCommand extends BaseCommand
{
    use ResolvesTunnel;

    /**
     * Configures the command name, description, and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('expose:configure')
            ->setDescription('Configure credentials and options for the tunnel service.')
            ->addOption('tunnel', 't', InputOption::VALUE_OPTIONAL, 'Override which tunnel to configure.')
            ->addOption(
                'reset', null, InputOption::VALUE_NONE, 'Reset the preferred tunnel choice stored in composer.json.'
            );
    }

    /**
     * Guides the user through tunnel configuration prompts and persists the results.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = app(SymfonyStyle::class);
        $config = app(ComposerConfig::class);

        if ($input->getOption('reset')) {
            $this->resetTunnelChoice($io, $config);

            return self::SUCCESS;
        }

        $tunnel = $this->resolveTunnel($io, $config, $input->getOption('tunnel'));

        $io->title("Configuring {$tunnel->name()}");

        if (!$tunnel instanceof InstallableTunnel || !$tunnel->isInstalled()) {
            $io->warning("{$tunnel->name()} does not appear to be installed. Configuration may not persist.");
        }

        $values = $this->promptOptions($io, $tunnel);

        $tunnel->configure($io, $values);

        $this->persistNonSecretOptions($config, $values);

        $io->success("{$tunnel->name()} configured successfully.");

        return self::SUCCESS;
    }

    /**
     * Prompts the user for each configurable option and returns the collected values.
     */
    protected function promptOptions(SymfonyStyle $io, Tunnel $tunnel): array
    {
        if ($options = $tunnel->configurableOptions()) {
            return array_map(function (Option $option) use ($io): ?string {
                return $this->promptSingleOption($io, $option);
            }, $options);
        }

        $io->note("{$tunnel->name()} has no configurable options.");

        return [];
    }

    /**
     * Prompts for a single option, hiding input when marked as a secret.
     */
    protected function promptSingleOption(SymfonyStyle $io, Option $option): ?string
    {
        if ($option->isNotRequired()) {
            return $option->default;
        }

        if ($option->isSecret) {
            return $io->askHidden("$option->label (leave blank to skip)") ?: null;
        }

        return $io->ask($option->label, $option->default);
    }

    /**
     * Persists non-null, non-secret option values to the `extra.expose.options` block.
     *
     * Sensitive values (marked secret) are intentionally never written to disk.
     */
    protected function persistNonSecretOptions(ComposerConfig $config, array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value !== null) {
                $config->set("options.$key", $value);
            }
        }
    }

    /**
     * Removes the saved tunnel preference from composer.json.
     */
    protected function resetTunnelChoice(SymfonyStyle $io, ComposerConfig $config): void
    {
        $config->forget('tunnel');

        $io->success('Tunnel preference reset. Run `composer expose` to choose again.');
    }
}
