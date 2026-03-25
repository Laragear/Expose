<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Http;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Tunnels\LocaltunnelTunnel;
use Mockery\MockInterface;
use Tests\TestCase;

/** Tests the LocaltunnelTunnel implementation. */
class LocaltunnelTunnelTest extends TestCase
{
    protected Http&MockInterface $http;
    protected ProcessFactory&MockInterface $process;
    protected LocaltunnelTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new LocaltunnelTunnel(
            $this->http = $this->mock(Http::class),
            $this->mock(BinaryManager::class),
            $this->process = $this->mock(ProcessFactory::class)
        );
    }

    public function test_name_is_localtunnel(): void
    {
        static::assertSame('Localtunnel', $this->tunnel->name());
    }

    public function test_binary_is_lt(): void
    {
        static::assertSame('lt', $this->tunnel->binary());
    }

    public function test_is_npm_package(): void
    {
        static::assertTrue($this->tunnel->isInstallableViaNpm());
    }

    public function test_npm_package_name_is_localtunnel(): void
    {
        static::assertSame('localtunnel', $this->tunnel->npmPackageName());
    }

    public function test_download_url_is_null(): void
    {
        static::assertNull($this->tunnel->downloadUrl());
    }

    public function test_configurable_options_contains_subdomain(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('subdomain', $options);
        static::assertFalse($options['subdomain']->isSecret);
    }
}
