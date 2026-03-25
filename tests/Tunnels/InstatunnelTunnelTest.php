<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Http;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Tunnels\InstatunnelTunnel;
use Mockery\MockInterface;
use Tests\TestCase;

/** Tests the InstatunnelTunnel implementation. */
class InstatunnelTunnelTest extends TestCase
{
    protected Http&MockInterface $http;
    protected ProcessFactory&MockInterface $process;
    protected InstatunnelTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new InstatunnelTunnel(
            $this->http = $this->mock(Http::class),
            $this->mock(BinaryManager::class),
            $this->process = $this->mock(ProcessFactory::class)
        );
    }

    public function test_name_is_instunnel(): void
    {
        static::assertSame('InstaTunnel', $this->tunnel->name());
    }

    public function test_binary_is_instatunnel(): void
    {
        static::assertSame('instatunnel', $this->tunnel->binary());
    }

    public function test_is_npm_package(): void
    {
        static::assertTrue($this->tunnel->isInstallableViaNpm());
    }

    public function test_npm_package_name_is_instatunnel(): void
    {
        static::assertSame('instatunnel', $this->tunnel->npmPackageName());
    }

    public function test_download_url_is_null(): void
    {
        static::assertNull($this->tunnel->downloadUrl());
    }

    public function test_configurable_options_has_secret_token(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('token', $options);
        static::assertTrue($options['token']->isSecret);
    }

    public function test_configurable_options_has_non_secret_subdomain(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('subdomain', $options);
        static::assertFalse($options['subdomain']->isSecret);
    }
}
