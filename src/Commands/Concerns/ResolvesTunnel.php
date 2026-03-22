<?php

declare(strict_types=1);

namespace Laragear\Expose\Commands\Concerns;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\TunnelRegistry;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared tunnel-resolution logic for all Expose commands.
 */
trait ResolvesTunnel
{
    /**
     * Builds and returns a TunnelRegistry seeded from the project root.
     * The registry merges built-ins, project-level, and package-level tunnels.
     */
    protected function registry(): TunnelRegistry
    {
        return new TunnelRegistry((string) getcwd());
    }

    /**
     * Resolves the Tunnel to use, following this priority order:
     *  1. --tunnel CLI option (if provided)
     *  2. extra.expose.tunnel in composer.json
     *  3. Interactive choice prompt (result is saved to composer.json)
     */
    protected function resolveTunnel(SymfonyStyle $io, ComposerConfig $config, mixed $override): Tunnel
    {
        $registry = $this->registry();

        $key = $this->resolveTunnelKey($io, $config, $registry, $override);

        return $registry->make($key);
    }

    /**
     * Returns the tunnel key from option/config/prompt, saving to disk when prompted.
     */
    protected function resolveTunnelKey(
        SymfonyStyle $io,
        ComposerConfig $config,
        TunnelRegistry $registry,
        mixed $override,
    ): string {
        if ($override !== null) {
            return (string) $override;
        }

        $saved = $config->get('tunnel');

        if ($saved !== null) {
            return (string) $saved;
        }

        return $this->promptTunnelChoice($io, $config, $registry);
    }

    /**
     * Asks the user to pick a tunnel, then saves the choice to composer.json.
     */
    protected function promptTunnelChoice(SymfonyStyle $io, ComposerConfig $config, TunnelRegistry $registry): string
    {
        $io->title('No tunnel service configured.');

        $key = $io->choice('Which tunnel service would you like to use?', $registry->choiceMap());

        $config->set('tunnel', $key);

        $io->success("Saved <info>$key</info> as your preferred tunnel.");

        return (string) $key;
    }

    /**
     * Returns the tunnel key from the override option or saved config.
     * Throws when neither is available, for commands that must not prompt.
     */
    protected function requireSavedTunnelKey(ComposerConfig $config, mixed $override): string
    {
        if ($raw = $override ?? $config->get('tunnel')) {
            return $raw;
        }

        throw new RuntimeException('No tunnel service configured. Run `composer expose` first.');
    }
}
