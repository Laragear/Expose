<?php

declare(strict_types=1);

namespace Tests\Commands;

use Laragear\Expose\Commands\Concerns\ResolvesTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\TunnelRegistry;
use Tests\TestCase;
use Laragear\Expose\Tunnels\AbstractTunnel;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Tests the ResolvesTunnel trait in isolation via an anonymous consumer class.
 */
class ResolvesTunnelTest extends TestCase
{
    /**
     * Builds a throwaway consumer of the trait bound to the given project root.
     */
    private function consumer(string $projectRoot): object
    {
        return new class($projectRoot) {
            use ResolvesTunnel;

            public function __construct(private readonly string $root)
            {
            }

            /**
             * Override so the registry resolves against our temp dir, not getcwd().
             */
            protected function registry(): TunnelRegistry
            {
                return new TunnelRegistry($this->root);
            }

            /**
             * Expose resolveTunnel publicly for testing.
             */
            public function callResolveTunnel(SymfonyStyle $io, ComposerConfig $config, mixed $override): Tunnel
            {
                return $this->resolveTunnel($io, $config, $override);
            }

            /**
             * Expose requireSavedTunnelKey publicly for testing.
             */
            public function callRequireSavedTunnelKey(ComposerConfig $config, mixed $override): string
            {
                return $this->requireSavedTunnelKey($config, $override);
            }
        };
    }

    /**
     * Builds a SymfonyStyle with a BufferedOutput for assertions.
     */
    private function io(?BufferedOutput &$out = null): SymfonyStyle
    {
        $out = new BufferedOutput();
        return new SymfonyStyle(new ArrayInput([]), $out);
    }

    /**
     * Writes a minimal composer.json and returns a ComposerConfig for it.
     */
    private function makeConfig(string $dir, array $expose = []): ComposerConfig
    {
        $data = ['name' => 'test/app', 'require' => new \stdClass()];

        if ($expose !== []) {
            $data['extra']['expose'] = $expose;
        }

        file_put_contents($dir.'/composer.json', json_encode($data, JSON_PRETTY_PRINT));

        return new ComposerConfig($dir.'/composer.json');
    }

    // -----------------------------------------------------------------------
    // resolveTunnel — override path
    // -----------------------------------------------------------------------

    public function test_resolve_tunnel_uses_override_option(): void
    {
        $this->withTempDir(function (string $dir): void {
            $config = $this->makeConfig($dir, ['tunnel' => 'zrok']);
            $consumer = $this->consumer($dir);

            // --tunnel=ngrok should win over the saved zrok preference
            $tunnel = $consumer->callResolveTunnel($this->io(), $config, 'ngrok');

            static::assertSame('ngrok', $tunnel->binary());
        });
    }

    // -----------------------------------------------------------------------
    // resolveTunnel — saved config path
    // -----------------------------------------------------------------------

    public function test_resolve_tunnel_reads_saved_config_when_no_override(): void
    {
        $this->withTempDir(function (string $dir): void {
            $config = $this->makeConfig($dir, ['tunnel' => 'cloudflare']);
            $consumer = $this->consumer($dir);

            $tunnel = $consumer->callResolveTunnel($this->io(), $config, null);

            static::assertSame('cloudflared', $tunnel->binary());
        });
    }

    // -----------------------------------------------------------------------
    // resolveTunnel — throws for unknown key
    // -----------------------------------------------------------------------

    public function test_resolve_tunnel_throws_for_unknown_service_key(): void
    {
        $this->withTempDir(function (string $dir): void {
            $config = $this->makeConfig($dir);
            $consumer = $this->consumer($dir);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Unknown tunnel service/');

            $consumer->callResolveTunnel($this->io(), $config, 'does-not-exist');
        });
    }

    // -----------------------------------------------------------------------
    // resolveTunnel — custom tunnel from project composer.json
    // -----------------------------------------------------------------------

    public function test_resolve_tunnel_resolves_custom_project_tunnel(): void
    {
        $this->withTempDir(function (string $dir): void {
            $config = $this->makeConfig($dir, [
                'tunnel' => 'my-custom',
                'tunnels' => [
                    'my-custom' => [
                        'label' => 'My Custom',
                        'class' => FakeTunnelForTraitTest::class,
                    ],
                ],
            ]);

            $consumer = $this->consumer($dir);
            $tunnel = $consumer->callResolveTunnel($this->io(), $config, null);

            static::assertInstanceOf(FakeTunnelForTraitTest::class, $tunnel);
        });
    }

    // -----------------------------------------------------------------------
    // requireSavedTunnelKey
    // -----------------------------------------------------------------------

    public function test_require_saved_key_returns_override_when_provided(): void
    {
        $this->withTempDir(function (string $dir): void {
            $config = $this->makeConfig($dir, ['tunnel' => 'ngrok']);
            $consumer = $this->consumer($dir);

            $key = $consumer->callRequireSavedTunnelKey($config, 'zrok');

            static::assertSame('zrok', $key);
        });
    }

    public function test_require_saved_key_returns_saved_config_key(): void
    {
        $this->withTempDir(function (string $dir): void {
            $config = $this->makeConfig($dir, ['tunnel' => 'pinggy']);
            $consumer = $this->consumer($dir);

            $key = $consumer->callRequireSavedTunnelKey($config, null);

            static::assertSame('pinggy', $key);
        });
    }

    public function test_require_saved_key_throws_when_nothing_configured(): void
    {
        $this->withTempDir(function (string $dir): void {
            $config = $this->makeConfig($dir);
            $consumer = $this->consumer($dir);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/No tunnel service configured/');

            $consumer->callRequireSavedTunnelKey($config, null);
        });
    }

    // -----------------------------------------------------------------------
    // registry() default binding
    // -----------------------------------------------------------------------

    public function test_registry_includes_all_built_in_services(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->makeConfig($dir);

            // Anonymous consumer that uses default registry() pointing at $dir
            $consumer = new class($dir) {
                use ResolvesTunnel;

                public function __construct(private readonly string $root)
                {
                }

                protected function registry(): TunnelRegistry
                {
                    return new TunnelRegistry($this->root);
                }

                public function getRegistry(): TunnelRegistry
                {
                    return $this->registry();
                }
            };

            $registry = $consumer->getRegistry();

            static::assertTrue($registry->has('ngrok'));
            static::assertTrue($registry->has('cloudflare'));
            static::assertTrue($registry->has('localtunnel'));
        });
    }
}

/** Minimal concrete tunnel used only in trait tests. */
class FakeTunnelForTraitTest extends AbstractTunnel
{
    public function name(): string
    {
        return 'Fake Trait Test';
    }

    public function binary(): string
    {
        return 'fake-trait';
    }

    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        return $this->buildProcess(['echo', 'fake']);
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'test-label';
    }
}
