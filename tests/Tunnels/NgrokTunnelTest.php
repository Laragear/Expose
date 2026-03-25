<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Http;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Tunnels\NgrokTunnel;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Tests the NgrokTunnel implementation. */
class NgrokTunnelTest extends TestCase
{
    protected Http&MockInterface $http;
    protected ProcessFactory&MockInterface $process;
    protected NgrokTunnel $tunnel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new NgrokTunnel(
            $this->http = $this->mock(Http::class),
            $this->mock(BinaryManager::class),
            $this->process = $this->mock(ProcessFactory::class)
        );
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
        static::assertTrue($options['authtoken']->isSecret);
    }

    public function test_configurable_options_contains_region(): void
    {
        $options = $this->tunnel->configurableOptions();

        static::assertArrayHasKey('region', $options);
        static::assertSame('us', $options['region']->default);
    }

    #[DataProvider('providesArchAndOs')]
    public function test_download_url_is_non_null_on_supported_platforms(string $os, string $arch): void
    {
        $this->process->expects('arch')->andReturn($arch);
        $this->process->expects('os')->andReturn($os);

        static::assertNotNull($this->tunnel->downloadUrl());
    }

    public function test_download_url_is_null_on_unsupported_platforms(): void
    {
        $this->process->expects('arch')->andReturn('invalid');
        $this->process->expects('os')->andReturn('invalid');

        static::assertNull($this->tunnel->downloadUrl());
    }

    public function test_status_returns_not_running_when_api_unreachable(): void
    {
        $this->http->expects('localGet')->with(4040, 'api/tunnels', false)->andReturnFalse();

        $status = $this->tunnel->status();

        static::assertFalse($status['running']);
        static::assertNull($status['url']);
    }
}
