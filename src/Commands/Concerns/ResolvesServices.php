<?php

namespace Laragear\Expose\Commands\Concerns;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\TunnelRegistry;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use function app;

trait ResolvesServices
{
    /**
     * Returns a decorate IO.
     */
    protected function io(): SymfonyStyle
    {
        return app(SymfonyStyle::class);
    }

    /**
     * Returns the Composer Config.
     */
    protected function config(): ComposerConfig
    {
        return app(ComposerConfig::class);
    }

    /**
     * Builds and returns a TunnelRegistry seeded from the project root.
     *
     * The registry merges built-ins, project-level, and package-level tunnels.
     */
    protected function registry(): TunnelRegistry
    {
        return app(TunnelRegistry::class);
    }

    /**
     * Resolves the Tunnel to use, following this priority order:
     *  1. --tunnel CLI option (if provided)
     *  2. extra.expose.tunnel in composer.json
     *  3. Interactive choice prompt (result is saved to composer.json)
     */
    protected function resolveTunnel(mixed $override): Tunnel
    {
        return $this->registry()->make($this->resolveTunnelKey($override));
    }

    /**
     * Returns the tunnel key from option/config/prompt, saving to disk when prompted.
     */
    protected function resolveTunnelKey(mixed $override): string
    {
        if ($override !== null) {
            return (string) $override;
        }

        $saved = $this->config()->get('tunnel');

        if ($saved !== null) {
            return (string) $saved;
        }

        return $this->promptTunnelChoice();
    }

    /**
     * Asks the user to pick a tunnel, then saves the choice to composer.json.
     */
    protected function promptTunnelChoice(): string
    {
        $this->io()->title('No tunnel service configured.');

        $key = (string) $this->io()->choice('Which tunnel service would you like to use?', $this->registry()->choiceMap());

        $this->config()->set('tunnel', $key);

        $this->io()->success("Saved <info>$key</info> as your preferred tunnel.");

        return $key;
    }

    /**
     * Returns the tunnel key from the override option or saved config.
     * Throws when neither is available, for commands that must not prompt.
     */
    protected function requireSavedTunnelKey(mixed $override): string
    {
        if ($raw = $override ?? $this->config()->get('tunnel')) {
            return $raw;
        }

        throw new RuntimeException('No tunnel service configured. Run `composer expose` first.');
    }
}
