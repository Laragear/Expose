<?php

declare(strict_types=1);

namespace Tests\Support;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Enums\TunnelService;
use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\File;
use Laragear\Expose\Support\TunnelRegistry;
use Laragear\Expose\Tunnels\AbstractTunnel;
use Laragear\Expose\Tunnels\NgrokTunnel;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use function json_encode;

class TunnelRegistryTest extends TestCase
{
    protected File&MockInterface $file;
    protected TunnelRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(BinaryManager::class);
        $this->file = $this->mock(File::class);
        $this->file->expects('missing')->with('/app/composer.json')->andReturnTrue();
        $this->file->expects('isNotDir')->with('/app/vendor')->andReturnTrue();

        $this->registry = new TunnelRegistry($this->file, '/app');
    }

    public function test_choice_map(): void
    {
        static::assertSame([
            'ngrok' => 'ngrok (ngrok.com)',
            'cloudflare' => 'Cloudflare Tunnel (cloudflared)',
            'instatunnel' => 'InsTunnel (instatunnel.com)',
            'localtunnel' => 'Localtunnel (localtunnel.me) [npm]',
            'pinggy' => 'Pinggy (pinggy.io) [ssh-based]',
            'zrok' => 'Zrok (zrok.io)',
        ], $this->registry->choiceMap());
    }

    public static function providesInvalidChoiceMap(): array
    {
        return [
            [['class' => NgrokTunnel::class]],
            [['label' => 'test-label']],
            [[]],
        ];
    }

    #[DataProvider('providesInvalidChoiceMap')]
    public function test_choice_map_fails_if_no_label(array $map): void
    {
        $file = $this->mock(File::class);
        $file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $file->expects('get')->with('/app/composer.json')->andReturn(json_encode([
            'extra' => [
                'expose' => [
                    'tunnels' => [
                        'test-key' => $map
                    ]
                ]
            ]
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Tunnel definition for [test-key] in [project composer.json] must have both 'label' and 'class' keys");

        new TunnelRegistry($file, '/app');
    }

    public function test_choice_map_fails_if_class_does_not_exist(): void
    {
        $file = $this->mock(File::class);
        $file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $file->expects('get')->with('/app/composer.json')->andReturn(json_encode([
            'extra' => [
                'expose' => [
                    'tunnels' => [
                        'test-key' => [
                            'label' => 'test-label',
                            'class' => '\Invalid\Class',
                        ]
                    ]
                ]
            ]
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tunnel class [\Invalid\Class] does not exist. Check your autoloader.');

        new TunnelRegistry($file, '/app');
    }

    public function test_choice_map_fails_if_class_does_not_implement_tunnel(): void
    {
        $file = $this->mock(File::class);
        $file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $file->expects('get')->with('/app/composer.json')->andReturn(json_encode([
            'extra' => [
                'expose' => [
                    'tunnels' => [
                        'test-key' => [
                            'label' => 'test-label',
                            'class' => static::class,
                        ]
                    ]
                ]
            ]
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tunnel class [Tests\Support\TunnelRegistryTest] must implement Laragear\Expose\Contracts\Tunnel.');

        new TunnelRegistry($file, '/app');
    }

    public function test_choice_map_includes_from_composer_json(): void
    {
        $file = $this->mock(File::class);
        $file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $file->expects('get')->with('/app/composer.json')->andReturn(json_encode([
            'extra' => [
                'expose' => [
                    'tunnels' => [
                        'test-key' => [
                            'label' => 'test-label',
                            'class' => NgrokTunnel::class,
                        ]
                    ]
                ]
            ]
        ]));
        $file->expects('isNotDir')->with('/app/vendor')->andReturnTrue();

        $registry = new TunnelRegistry($file, '/app');

        static::assertSame([
            'ngrok' => 'ngrok (ngrok.com)',
            'cloudflare' => 'Cloudflare Tunnel (cloudflared)',
            'instatunnel' => 'InsTunnel (instatunnel.com)',
            'localtunnel' => 'Localtunnel (localtunnel.me) [npm]',
            'pinggy' => 'Pinggy (pinggy.io) [ssh-based]',
            'zrok' => 'Zrok (zrok.io)',
            'test-key' => 'test-label'
        ], $registry->choiceMap());
    }


    #[DataProvider('providesInvalidChoiceMap')]
    public function test_loads_from_package_fails_if_empty(array $invalid): void
    {
        $this->expectNotToPerformAssertions();

        $file = $this->mock(File::class);
        $file->expects('missing')->with('/app/composer.json')->andReturnTrue();
        $file->expects('isNotDir')->with('/app/vendor')->andReturnFalse();
        $file->expects('glob')->with('/app/vendor/*/*/composer.json')->andReturn([
            '/app/vendor/foo-vendor/foo-package/composer.json',
            '/app/vendor/bar-vendor/bar-package/composer.json',
        ]);
        $file->expects('missing')->with('/app/vendor/foo-vendor/foo-package/composer.json')->andReturnFalse();
        $file->expects('get')->with('/app/vendor/foo-vendor/foo-package/composer.json')->andReturn(json_encode([
            'extra' => [
                'expose-tunnel' => [
                    'key' => 'package-tunnel-key',
                    ...$invalid
                ]
            ]
        ]));
        $file->expects('missing')->with('/app/vendor/bar-vendor/bar-package/composer.json')->andReturnTrue();

        $this->mock(SymfonyStyle::class)
            ->expects('warning')
            ->with("Tunnel definition for [package-tunnel-key] in [/app/vendor/foo-vendor/foo-package/composer.json] must have both 'label' and 'class' keys.");

        new TunnelRegistry($file, '/app');
    }

    public function test_loads_from_package_fails_if_class_does_not_exists(): void
    {
        $this->expectNotToPerformAssertions();

        $file = $this->mock(File::class);
        $file->expects('missing')->with('/app/composer.json')->andReturnTrue();
        $file->expects('isNotDir')->with('/app/vendor')->andReturnFalse();
        $file->expects('glob')->with('/app/vendor/*/*/composer.json')->andReturn([
            '/app/vendor/foo-vendor/foo-package/composer.json',
            '/app/vendor/bar-vendor/bar-package/composer.json',
        ]);
        $file->expects('missing')->with('/app/vendor/foo-vendor/foo-package/composer.json')->andReturnFalse();
        $file->expects('get')->with('/app/vendor/foo-vendor/foo-package/composer.json')->andReturn(json_encode([
            'extra' => [
                'expose-tunnel' => [
                    'key' => 'package-tunnel-key',
                    'label' => 'package-label',
                    'class' => '\Invalid\Class',
                ]
            ]
        ]));
        $file->expects('missing')->with('/app/vendor/bar-vendor/bar-package/composer.json')->andReturnTrue();

        $this->mock(SymfonyStyle::class)
            ->expects('warning')
            ->with("Tunnel class [\Invalid\Class] does not exist. Check your autoloader.");

        new TunnelRegistry($file, '/app');
    }

    public function test_loads_from_package_fails_if_class_does_not_implement_tunnel(): void
    {
        $this->expectNotToPerformAssertions();

        $file = $this->mock(File::class);
        $file->expects('missing')->with('/app/composer.json')->andReturnTrue();
        $file->expects('isNotDir')->with('/app/vendor')->andReturnFalse();
        $file->expects('glob')->with('/app/vendor/*/*/composer.json')->andReturn([
            '/app/vendor/foo-vendor/foo-package/composer.json',
            '/app/vendor/bar-vendor/bar-package/composer.json',
        ]);
        $file->expects('missing')->with('/app/vendor/foo-vendor/foo-package/composer.json')->andReturnFalse();
        $file->expects('get')->with('/app/vendor/foo-vendor/foo-package/composer.json')->andReturn(json_encode([
            'extra' => [
                'expose-tunnel' => [
                    'key' => 'package-tunnel-key',
                    'label' => 'package-label',
                    'class' => static::class,
                ]
            ]
        ]));
        $file->expects('missing')->with('/app/vendor/bar-vendor/bar-package/composer.json')->andReturnTrue();

        $this->mock(SymfonyStyle::class)
            ->expects('warning')
            ->with("Tunnel class [Tests\Support\TunnelRegistryTest] must implement Laragear\Expose\Contracts\Tunnel.");

        new TunnelRegistry($file, '/app');
    }

    public function test_loads_from_package(): void
    {
        $file = $this->mock(File::class);
        $file->expects('missing')->with('/app/composer.json')->andReturnTrue();
        $file->expects('isNotDir')->with('/app/vendor')->andReturnFalse();
        $file->expects('glob')->with('/app/vendor/*/*/composer.json')->andReturn([
            '/app/vendor/foo-vendor/foo-package/composer.json',
            '/app/vendor/bar-vendor/bar-package/composer.json',
        ]);
        $file->expects('missing')->with('/app/vendor/foo-vendor/foo-package/composer.json')->andReturnTrue();
        $file->expects('missing')->with('/app/vendor/bar-vendor/bar-package/composer.json')->andReturnFalse();
        $file->expects('get')->with('/app/vendor/bar-vendor/bar-package/composer.json')->andReturn(json_encode([
            'extra' => [
                'expose-tunnel' => [
                    'key' => 'package-tunnel-key',
                    'label' => 'package-label',
                    'class' => NgrokTunnel::class,
                ]
            ]
        ]));

        $registry = new TunnelRegistry($file, '/app');

        static::assertSame([
            'ngrok' => 'ngrok (ngrok.com)',
            'cloudflare' => 'Cloudflare Tunnel (cloudflared)',
            'instatunnel' => 'InsTunnel (instatunnel.com)',
            'localtunnel' => 'Localtunnel (localtunnel.me) [npm]',
            'pinggy' => 'Pinggy (pinggy.io) [ssh-based]',
            'zrok' => 'Zrok (zrok.io)',
            'package-tunnel-key' => 'package-label'
        ], $registry->choiceMap());
    }

    public function test_has(): void
    {
        static::assertTrue($this->registry->has('ngrok'));
        static::assertFalse($this->registry->has('invalid'));
    }

    public function test_missing(): void
    {
        static::assertFalse($this->registry->missing('ngrok'));
        static::assertTrue($this->registry->missing('invalid'));
    }

    public function test_make_fails_if_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown tunnel service: [missing]. Register it under extra.expose.tunnels in your composer.json.');

        $this->registry->make('missing');
    }

    public function test_make_uses_container(): void
    {
        $tunnel = $this->mock(NgrokTunnel::class);

        static::assertSame($tunnel, $this->registry->make('ngrok'));
    }

    public function test_keys(): void
    {
        static::assertSame(
            ['ngrok', 'cloudflare', 'instatunnel', 'localtunnel', 'pinggy', 'zrok'],
            $this->registry->keys()
        );
    }

    public function test_register_fails_if_class_does_not_implement_tunnel(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tunnel class [Tests\Support\TunnelRegistryTest] must implement Laragear\Expose\Contracts\Tunnel');

        $this->registry->register('test-key', 'test-label', static::class);
    }

    public function test_register(): void
    {
        $this->registry->register('test-key', 'test-label', NgrokTunnel::class);

        static::assertTrue($this->registry->has('test-key'));
    }
}
