<?php

declare(strict_types=1);

namespace Laragear\Expose\Support;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Enums\TunnelService;
use RuntimeException;
use function app;
use function array_column;
use function array_combine;
use function get_class;
use function getcwd;
use function method_exists;
use const DIRECTORY_SEPARATOR as DS;

/**
 * Builds the authoritative map of available tunnel services by merging built-ins
 * with any custom tunnels declared in project or package composer.json files.
 *
 * Tunnels may be registered from two sources, in resolution order:
 *
 *  1. Built-in services defined by the TunnelService enum.
 *  2. Project-level entries under `extra.expose.tunnels` in the root composer.json.
 *  3. Package-level entries under `extra.expose-tunnel` in any installed package.
 */
class TunnelRegistry
{
    /**
     * The composer.json key inside `extra.expose` where custom tunnels are declared.
     */
    protected const string PROJECT_KEY = 'tunnels';

    /**
     * The top-level composer.json key used by installable tunnel packages.
     */
    protected const string PACKAGE_KEY = 'expose-tunnel';

    /**
     * The resolved tunnel map: key => [label, factory].
     *
     * @var array<string, array{label: string, class: class-string<Tunnel>}>
     */
    protected array $tunnels = [];

    /**
     * Create a new Tunnel Registry instance.
     *
     * @param  string  $projectRoot  The absolute path to the project root (where composer.json lives).
     */
    public function __construct(
        protected File $file,
        protected readonly string $projectRoot)
    {
        $this->loadBuiltIns();
        $this->loadFromProjectComposerJson();
        $this->loadFromInstalledPackages();
    }

    /**
     * Returns all registered tunnels as a label => key map for choice prompts.
     *
     * @return array<string, string>
     */
    public function choiceMap(): array
    {
        return array_combine(array_keys($this->tunnels), array_column($this->tunnels, 'label'));
    }

    /**
     * Check if a tunnel was registered.
     */
    public function has(string $key): bool
    {
        return isset($this->tunnels[$key]);
    }

    /**
     * Check if the tunnel does not exists.
     */
    public function missing(string $key): bool
    {
        return ! $this->has($key);
    }

    /**
     * Instantiates and returns the Tunnel for the given key.
     */
    public function make(string $key): Tunnel
    {
        if ($this->missing($key)) {
            throw new RuntimeException(
                "Unknown tunnel service: [$key]. Register it under extra.expose.tunnels in your composer.json.",
            );
        }

        if (!isset($this->tunnels[$key]['class'])) {
            throw new RuntimeException("The Tunnel Service [$key] does not have a valid class.");
        }

        $class = $this->tunnels[$key]['class'];

        $container = app();

        if (method_exists($class, 'withContainer')) {
            $class->withContainer($container);
        }

        return $container->make($class);
    }

    /**
     * Returns all registered keys.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->tunnels);
    }

    /**
     * Registers a tunnel under the given key with a human-readable label.
     */
    public function register(string $key, string $label, string $class): void
    {
        $this->assertImplementsTunnel($class);

        $this->tunnels[$key] = ['label' => $label, 'class' => $class];
    }

    /**
     * Seeds the registry with every TunnelService enum case.
     */
    protected function loadBuiltIns(): void
    {
        foreach (TunnelService::cases() as $service) {
            $tunnel = $service->make();

            $this->tunnels[$service->value] = [
                'label' => $tunnel->label(),
                'class' => get_class($tunnel),
            ];
        }
    }

    /**
     * Reads `extra.expose.tunnels` from the root composer.json and registers each entry.
     *
     * Expected format:
     * ```json
     * {
     *   "extra": {
     *     "expose": {
     *       "tunnels": {
     *         "my-service": {
     *           "label": "My Custom Tunnel",
     *           "class": "MyVendor\\MyPackage\\MyTunnel"
     *         }
     *       }
     *     }
     *   }
     * }
     * ```
     */
    protected function loadFromProjectComposerJson(): void
    {
        $data = $this->readJson($this->projectRoot.DS.'composer.json');

        $tunnels = $data['extra']['expose'][self::PROJECT_KEY] ?? [];

        foreach ($tunnels as $key => $definition) {
            $this->registerFromDefinition((string) $key, $definition, 'project composer.json');
        }
    }

    /**
     * Scans every installed package's composer.json for `extra.expose-tunnel` and
     * registers the declared tunnel if found.
     *
     * Package-level format:
     * ```json
     * {
     *   "extra": {
     *     "expose-tunnel": {
     *       "key":   "my-service",
     *       "label": "My Service (vendor/my-package)",
     *       "class": "MyVendor\\MyPackage\\MyTunnel"
     *     }
     *   }
     * }
     * ```
     */
    protected function loadFromInstalledPackages(): void
    {
        $vendorDir = $this->projectRoot.DS.'vendor';

        if (!is_dir($vendorDir)) {
            return;
        }

        foreach ($this->vendorComposerJsonPaths($vendorDir) as $path) {
            $data = $this->readJson($path);

            $definition = $data['extra'][self::PACKAGE_KEY] ?? null;

            if (!is_array($definition) || empty($definition['key'])) {
                continue;
            }

            $this->registerFromDefinition(
                (string) $definition['key'],
                $definition,
                (string) $path,
            );
        }
    }

    /**
     * Validates and registers a single tunnel definition array.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function registerFromDefinition(string $key, array $definition, string $source): void
    {
        if (empty($definition['label']) || empty($definition['class'])) {
            throw new RuntimeException(
                "Tunnel definition for [$key] in [$source] must have both 'label' and 'class' keys.",
            );
        }

        $this->register($key, (string) $definition['label'], (string) $definition['class']);
    }

    /**
     * Yields the path to every composer.json found one level deep inside vendor/.
     *
     * @return iterable<string>
     */
    protected function vendorComposerJsonPaths(string $vendorDir): iterable
    {
        foreach (glob($vendorDir.DS.'*'.DS.'*'.DS.'composer.json') ?: [] as $path) {
            yield $path;
        }
    }

    /**
     * Reads and JSON-decodes a file, returning an empty array on failure.
     *
     * @return array<string, mixed>
     */
    protected function readJson(string $path): array
    {
        if ($this->file->missing($path)) {
            return [];
        }

        return json_decode($this->file->get($path), true) ?: [];
    }

    /**
     * Asserts that the given class name implements the Tunnel contract.
     *
     * @param  class-string  $class
     */
    protected function assertImplementsTunnel(string $class): void
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Tunnel class [$class] does not exist. Check your autoloader.");
        }

        if (!is_a($class, Tunnel::class, true)) {
            throw new RuntimeException("Tunnel class [$class] must implement ".Tunnel::class.'.',);
        }
    }
}
