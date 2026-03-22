<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Tests\TestCase;
use Laragear\Expose\Tunnels\InstatunnelTunnel;

/** Tests the InstatunnelTunnel implementation. */
class InstatunnelTunnelTest extends TestCase
{
    private InstatunnelTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new InstatunnelTunnel();
    }

    public function test_name_is_instunnel(): void
    {
        static::assertSame('InsTunnel', $this->tunnel->name());
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
        static::assertTrue($options['token']['secret']);
    }

    public function test_configurable_options_has_non_secret_subdomain(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('subdomain', $options);
        static::assertFalse($options['subdomain']['secret']);
    }
}
