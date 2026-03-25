<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Http;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Tunnels\PinggyTunnel;
use Mockery\MockInterface;
use Tests\TestCase;

/** Tests the PinggyTunnel SSH-based implementation. */
class PinggyTunnelTest extends TestCase
{
    protected Http&MockInterface $http;
    protected ProcessFactory&MockInterface $process;
    protected PinggyTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new PinggyTunnel(
            $this->http = $this->mock(Http::class),
            $this->mock(BinaryManager::class),
            $this->process = $this->mock(ProcessFactory::class)
        );
    }

    public function test_name_is_pinggy(): void
    {
        static::assertSame('Pinggy', $this->tunnel->name());
    }

    public function test_binary_is_ssh(): void
    {
        static::assertSame('ssh', $this->tunnel->binary());
    }

    public function test_is_not_npm_package(): void
    {
        static::assertFalse($this->tunnel->isInstallableViaNpm());
    }

    public function test_npm_package_name_is_null(): void
    {
        static::assertNull($this->tunnel->npmPackageName());
    }

    public function test_download_url_is_null(): void
    {
        // Pinggy uses the system SSH — no binary to download
        static::assertNull($this->tunnel->downloadUrl());
    }

    public function test_configurable_options_has_token(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('token', $options);
        static::assertTrue($options['token']->isSecret);
    }

    public function test_configurable_options_has_subdomain(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('subdomain', $options);
        static::assertFalse($options['subdomain']->isSecret);
    }
}
