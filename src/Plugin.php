<?php

declare(strict_types=1);

namespace Laragear\Expose;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;

/**
 * Registers the Expose plugin and its command provider into Composer.
 */
class Plugin implements PluginInterface, Capable
{
    /**
     * Activates the plugin (no-op: all logic lives in commands).
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        //
    }

    /**
     * Deactivates the plugin (no-op).
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        //
    }

    /**
     * Uninstalls the plugin (no-op).
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        //
    }

    /**
     * Declares the capabilities provided by this plugin.
     *
     * @return array<string, string>
     */
    public function getCapabilities(): array
    {
        return [
            CommandProvider::class => ExposeCommandProvider::class,
        ];
    }
}
