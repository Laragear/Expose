<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands;

use Composer\Command\BaseCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\Option;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function array_map;

/**
 * Interactively configures the tunnel service credentials and common options.
 */
class ConfigureCommand extends BaseCommand
{
    use Concerns\ResolvesServices;

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
        if ($input->getOption('reset')) {
            $this->resetTunnelChoice();

            return self::SUCCESS;
        }

        $tunnel = $this->resolveTunnel($input->getOption('tunnel'));

        $this->io()->title("Configuring {$tunnel->name()}");

        if (!$tunnel instanceof InstallableTunnel || !$tunnel->isInstalled()) {
            $this->io()->warning("{$tunnel->name()} does not appear to be installed. Configuration may not persist.");
        }

        $values = $this->promptOptions($tunnel);

        $tunnel->configure($this->io(), $values);

        $this->persistNonSecretOptions($values);

        $this->io()->success("{$tunnel->name()} configured successfully.");

        return self::SUCCESS;
    }

    /**
     * Prompts the user for each configurable option and returns the collected values.
     */
    protected function promptOptions(Tunnel $tunnel): array
    {
        if ($options = $tunnel->configurableOptions()) {
            return array_map(function (Option $option): ?string {
                return $this->promptSingleOption($option);
            }, $options);
        }

        $this->io()->note("{$tunnel->name()} has no configurable options.");

        return [];
    }

    /**
     * Prompts for a single option, hiding input when marked as a secret.
     */
    protected function promptSingleOption(Option $option): ?string
    {
        if ($option->isNotRequired()) {
            return $option->default;
        }

        if ($option->isSecret) {
            return $this->io()->askHidden("$option->label (leave blank to skip)") ?: null;
        }

        return $this->io()->ask($option->label, $option->default);
    }

    /**
     * Persists non-null, non-secret option values to the `extra.expose.options` block.
     *
     * Sensitive values (marked secret) are intentionally never written to disk.
     */
    protected function persistNonSecretOptions(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value !== null) {
                $this->config()->set("options.$key", $value);
            }
        }
    }

    /**
     * Removes the saved tunnel preference from composer.json.
     */
    protected function resetTunnelChoice(): void
    {
        $this->config()->forget('tunnel');

        $this->io()->success('Tunnel preference reset. Run `composer expose` to choose again.');
    }
}
