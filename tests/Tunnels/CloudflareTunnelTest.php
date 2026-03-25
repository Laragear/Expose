<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Http;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Tunnels\CloudflareTunnel;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CloudflareTunnelTest extends TestCase
{
    protected Http&MockInterface $http;
    protected ProcessFactory&MockInterface $process;
    protected CloudflareTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new CloudflareTunnel(
            $this->http = $this->mock(Http::class),
            $this->mock(BinaryManager::class),
            $this->process = $this->mock(ProcessFactory::class)
        );
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
        static::assertTrue($options['token']->isSecret);
    }

    public function test_configurable_options_contains_hostname(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('hostname', $options);
        static::assertFalse($options['hostname']->isSecret);
    }

    #[DataProvider('providesArchAndOs')]
    public function test_download_url_is_non_null_on_supported_platforms(string $os, string $arch): void
    {
        $this->process->expects('arch')->andReturn($arch);
        $this->process->expects('os')->andReturn($os);

        static::assertNotNull($this->tunnel->downloadUrl());
    }

    #[DataProvider('providesArchAndOs')]
    public function test_download_url_contains_cloudflared(string $os, string $arch): void
    {
        $this->process->expects('arch')->andReturn($arch);
        $this->process->expects('os')->andReturn($os);

        static::assertStringContainsString('cloudflared', (string) $this->tunnel->downloadUrl());
    }

    public function test_download_url_is_null_when_unsupported_platform(): void
    {
        $this->process->expects('arch')->andReturn('invalid');
        $this->process->expects('os')->andReturn('invalid');

        static::assertNull($this->tunnel->downloadUrl());
    }

    public function test_default_status_is_not_running(): void
    {
        $this->http->expects('localGet')->with(20241, 'ready')->andReturnFalse();

        $status = $this->tunnel->status();

        static::assertFalse($status['running']);
        static::assertNull($status['url']);
    }
}
