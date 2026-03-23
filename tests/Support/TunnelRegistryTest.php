<?php

declare(strict_types=1);

namespace Tests\Support;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Enums\TunnelService;
use Laragear\Expose\Support\TunnelRegistry;
use Laragear\Expose\Tunnels\AbstractTunnel;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Tests TunnelRegistry built-in loading, project-level discovery, and package-level discovery. */
class TunnelRegistryTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Builds a registry pointing at a temp dir with a minimal composer.json. */
    private function makeRegistry(string $dir, array $expose = []): TunnelRegistry
    {
        $data = ['name' => 'test/app', 'require' => new \stdClass()];

        if ($expose !== []) {
            $data['extra']['expose'] = $expose;
        }

        file_put_contents($dir.'/composer.json', json_encode($data, JSON_PRETTY_PRINT));

        return new TunnelRegistry($dir);
    }

    /** Writes a fake installed-package composer.json with an expose-tunnel declaration. */
    private function writeFakePackage(string $vendorDir, string $vendor, string $name, array $definition): void
    {
        $packageDir = $vendorDir."/{$vendor}/{$name}";
        mkdir($packageDir, 0755, true);

        file_put_contents(
            $packageDir.'/composer.json',
            json_encode(['name' => "{$vendor}/{$name}", 'extra' => ['expose-tunnel' => $definition]], JSON_PRETTY_PRINT),
        );
    }

    // -----------------------------------------------------------------------
    // Built-in loading
    // -----------------------------------------------------------------------

    public function test_all_built_in_services_are_registered(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir);

            foreach (TunnelService::cases() as $service) {
                static::assertTrue($registry->has($service->value), "Missing built-in: {$service->value}");
            }
        });
    }

    public function test_make_returns_tunnel_instance_for_built_in(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir);

            $tunnel = $registry->make('ngrok');

            static::assertInstanceOf(Tunnel::class, $tunnel);
        });
    }

    public function test_choice_map_includes_all_built_ins(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir);
            $map = $registry->choiceMap();

            foreach (TunnelService::cases() as $service) {
                static::assertArrayHasKey($service->value, $map);
            }
        });
    }

    // -----------------------------------------------------------------------
    // Project-level custom tunnels
    // -----------------------------------------------------------------------

    public function test_custom_tunnel_registered_from_project_composer_json(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir, [
                'tunnels' => [
                    'my-tunnel' => [
                        'label' => 'My Custom Tunnel',
                        'class' => FakeCustomTunnel::class,
                    ],
                ],
            ]);

            static::assertTrue($registry->has('my-tunnel'));
        });
    }

    public function test_custom_tunnel_make_returns_correct_instance(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir, [
                'tunnels' => [
                    'my-tunnel' => [
                        'label' => 'My Custom Tunnel',
                        'class' => FakeCustomTunnel::class,
                    ],
                ],
            ]);

            $tunnel = $registry->make('my-tunnel');

            static::assertInstanceOf(FakeCustomTunnel::class, $tunnel);
        });
    }

    public function test_custom_tunnel_appears_in_choice_map(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir, [
                'tunnels' => [
                    'my-tunnel' => [
                        'label' => 'My Custom Tunnel',
                        'class' => FakeCustomTunnel::class,
                    ],
                ],
            ]);

            static::assertArrayHasKey('my-tunnel', $registry->choiceMap());
            static::assertContains('My Custom Tunnel', $registry->choiceMap());
        });
    }

    public function test_custom_tunnel_overrides_built_in_with_same_key(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir, [
                'tunnels' => [
                    'ngrok' => [
                        'label' => 'Custom ngrok-compatible',
                        'class' => FakeCustomTunnel::class,
                    ],
                ],
            ]);

            // The ngrok key should now resolve to our fake
            $tunnel = $registry->make('ngrok');

            static::assertInstanceOf(FakeCustomTunnel::class, $tunnel);
        });
    }

    public function test_throws_when_custom_class_does_not_exist(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/does not exist/');

            $this->makeRegistry($dir, [
                'tunnels' => [
                    'bad' => ['label' => 'Bad', 'class' => 'NonExistent\\ClassName'],
                ],
            ]);
        });
    }

    public function test_throws_when_custom_class_does_not_implement_tunnel(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/must implement/');

            $this->makeRegistry($dir, [
                'tunnels' => [
                    'bad' => ['label' => 'Bad', 'class' => \stdClass::class],
                ],
            ]);
        });
    }

    public function test_throws_when_definition_is_missing_label(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/must have both/');

            $this->makeRegistry($dir, [
                'tunnels' => [
                    'bad' => ['class' => FakeCustomTunnel::class],
                ],
            ]);
        });
    }

    // -----------------------------------------------------------------------
    // Package-level discovery
    // -----------------------------------------------------------------------

    public function test_tunnel_discovered_from_installed_package(): void
    {
        $this->withTempDir(function (string $dir): void {
            $vendorDir = $dir.'/vendor';

            $this->writeFakePackage($vendorDir, 'acme', 'my-tunnel', [
                'key' => 'acme-tunnel',
                'label' => 'Acme Tunnel',
                'class' => FakeCustomTunnel::class,
            ]);

            $registry = $this->makeRegistry($dir);

            static::assertTrue($registry->has('acme-tunnel'));
            static::assertInstanceOf(FakeCustomTunnel::class, $registry->make('acme-tunnel'));
        });
    }

    public function test_package_without_expose_tunnel_key_is_ignored(): void
    {
        $this->withTempDir(function (string $dir): void {
            $vendorDir = $dir.'/vendor';
            $packageDir = $vendorDir.'/vendor/other-package';
            mkdir($packageDir, 0755, true);

            file_put_contents(
                $packageDir.'/composer.json',
                json_encode(['name' => 'vendor/other-package'], JSON_PRETTY_PRINT),
            );

            $registry = $this->makeRegistry($dir);

            // Should load without error and only have the built-ins
            static::assertCount(count(TunnelService::cases()), $registry->keys());
        });
    }

    public function test_make_throws_for_unregistered_key(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Unknown tunnel service/');

            $registry->make('does-not-exist');
        });
    }

    // -----------------------------------------------------------------------
    // Manual registration
    // -----------------------------------------------------------------------

    public function test_register_adds_tunnel_to_registry(): void
    {
        $this->withTempDir(function (string $dir): void {
            $registry = $this->makeRegistry($dir);
            $registry->register('manual', 'Manual Tunnel', FakeCustomTunnel::class);

            static::assertTrue($registry->has('manual'));
            static::assertInstanceOf(FakeCustomTunnel::class, $registry->make('manual'));
        });
    }
}

/** A minimal concrete tunnel used only in tests. */
class FakeCustomTunnel extends AbstractTunnel
{
    public function name(): string
    {
        return 'Fake Custom';
    }

    public function binary(): string
    {
        return 'fake';
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
        return 'test-fake label';
    }
}
