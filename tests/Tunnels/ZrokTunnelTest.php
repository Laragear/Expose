<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Tunnels\ZrokTunnel;
use Tests\TestCase;

/** Tests the ZrokTunnel implementation. */
class ZrokTunnelTest extends TestCase
{
    private ZrokTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new ZrokTunnel();
    }

    public function test_name_is_zrok(): void
    {
        static::assertSame('Zrok', $this->tunnel->name());
    }

    public function test_binary_is_zrok(): void
    {
        static::assertSame('zrok', $this->tunnel->binary());
    }

    public function test_is_not_npm_package(): void
    {
        static::assertFalse($this->tunnel->isInstallableViaNpm());
    }

    public function test_npm_package_name_is_null(): void
    {
        static::assertNull($this->tunnel->npmPackageName());
    }

    public function test_download_url_is_non_null_on_supported_platforms(): void
    {
        if (! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin', 'Windows'], true)) {
            $this->markTestSkipped('Platform not supported.');
        }

        static::assertNotNull($this->tunnel->downloadUrl());
    }

    public function test_download_url_contains_zrok(): void
    {
        if (! in_array(PHP_OS_FAMILY, ['Linux', 'Darwin', 'Windows'], true)) {
            $this->markTestSkipped('Platform not supported.');
        }

        static::assertStringContainsString('zrok', (string) $this->tunnel->downloadUrl());
    }

    public function test_download_url_reflects_arm64_architecture(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || php_uname('m') !== 'arm64') {
            $this->markTestSkipped('arm64 non-Windows only.');
        }

        static::assertStringContainsString('arm64', (string) $this->tunnel->downloadUrl());
    }

    public function test_configurable_options_has_secret_token(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('token', $options);
        static::assertTrue($options['token']['secret']);
    }

    public function test_configurable_options_has_share_mode(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('share_mode', $options);
        static::assertSame('public', $options['share_mode']['default']);
        static::assertFalse($options['share_mode']['secret']);
    }

    public function test_default_status_is_not_running(): void
    {
        $status = $this->tunnel->status();

        static::assertFalse($status['running']);
        static::assertNull($status['url']);
    }
}
