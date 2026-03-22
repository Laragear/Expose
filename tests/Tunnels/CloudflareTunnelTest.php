<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Tests\TestCase;
use Laragear\Expose\Tunnels\CloudflareTunnel;

/** Tests the CloudflareTunnel implementation. */
class CloudflareTunnelTest extends TestCase
{
    private CloudflareTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new CloudflareTunnel();
    }

    public function test_name_is_cloudflare_tunnel(): void
    {
        static::assertSame('Cloudflare Tunnel', $this->tunnel->name());
    }

    public function test_binary_is_cloudflared(): void
    {
        static::assertSame('cloudflared', $this->tunnel->binary());
    }

    public function test_is_not_npm_package(): void
    {
        static::assertFalse($this->tunnel->isInstallableViaNpm());
    }

    public function test_npm_package_name_is_null(): void
    {
        static::assertNull($this->tunnel->npmPackageName());
    }

    public function test_configurable_options_contains_token(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('token', $options);
        static::assertTrue($options['token']['secret']);
    }

    public function test_configurable_options_contains_hostname(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('hostname', $options);
        static::assertFalse($options['hostname']['secret']);
    }

    public function test_download_url_is_non_null_on_supported_platforms(): void
    {
        if (! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin', 'Windows'], true)) {
            $this->markTestSkipped('Platform not supported.');
        }

        static::assertNotNull($this->tunnel->downloadUrl());
    }

    public function test_download_url_contains_cloudflared(): void
    {
        if (! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin', 'Windows'], true)) {
            $this->markTestSkipped('Platform not supported.');
        }

        static::assertStringContainsString('cloudflared', (string) $this->tunnel->downloadUrl());
    }

    public function test_default_status_is_not_running(): void
    {
        $status = $this->tunnel->status();

        static::assertFalse($status['running']);
        static::assertNull($status['url']);
    }
}
