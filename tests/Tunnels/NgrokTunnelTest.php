<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Tunnels\NgrokTunnel;
use Tests\TestCase;

/** Tests the NgrokTunnel implementation. */
class NgrokTunnelTest extends TestCase
{
    protected NgrokTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new NgrokTunnel();
    }

    public function test_name_is_ngrok(): void
    {
        static::assertSame('ngrok', $this->tunnel->name());
    }

    public function test_binary_is_ngrok(): void
    {
        static::assertSame('ngrok', $this->tunnel->binary());
    }

    public function test_is_not_npm_package(): void
    {
        static::assertFalse($this->tunnel->isInstallableViaNpm());
    }

    public function test_npm_package_name_is_null(): void
    {
        static::assertNull($this->tunnel->npmPackageName());
    }

    public function test_configurable_options_contains_authtoken(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('authtoken', $options);
        static::assertTrue($options['authtoken']['secret']);
    }

    public function test_configurable_options_contains_region(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('region', $options);
        static::assertSame('us', $options['region']['default']);
    }

    public function test_download_url_is_non_null_on_supported_platforms(): void
    {
        if (! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin', 'Windows'], true)) {
            $this->markTestSkipped('Platform not supported.');
        }

        static::assertNotNull($this->tunnel->downloadUrl());
    }

    public function test_status_returns_not_running_when_api_unreachable(): void
    {
        // With no ngrok running, the local API will be unreachable
        $status = $this->tunnel->status();

        static::assertFalse($status['running']);
        static::assertNull($status['url']);
    }
}
