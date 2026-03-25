<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Http;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Tunnels\ZrokTunnel;
use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use function strtolower;

/**
 * Tests the ZrokTunnel implementation.
 */
class ZrokTunnelTest extends TestCase
{
    protected ZrokTunnel $tunnel;

    protected Http&m\MockInterface $http;
    protected BinaryManager $binaryManager;
    protected ProcessFactory $processFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tunnel = new ZrokTunnel(
            $this->http = m::mock(Http::class),
            $this->binaryManager = m::mock(BinaryManager::class),
            $this->processFactory = m::mock(ProcessFactory::class),
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        m::close();
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

    #[DataProvider('providesArchAndOs')]
    public function test_download_url_is_non_null_on_supported_platforms(string $os, string $arch): void
    {
        $this->processFactory->expects('arch')->andReturn($arch);
        $this->processFactory->expects('os')->andReturn($os);

        $os = strtolower($os);

        $extension = $os === 'windows' ? 'zip' : 'tar.gz';

        static::assertSame(
            "https://github.com/openziti/zrok/releases/latest/download/zrok_{$os}_$arch.$extension",
            $this->tunnel->downloadUrl()
        );
    }

    public function test_download_url_is_null_on_unsupported_platform(): void
    {
        $this->processFactory->expects('arch')->andReturn('amd64');
        $this->processFactory->expects('os')->andReturn('invalid_is');

        static::assertNull($this->tunnel->downloadUrl());
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
        $this->http->expects('localGet')->with(9191, '/api/v1/overview')->andReturnFalse();

        $status = $this->tunnel->status();

        static::assertFalse($status['running']);
        static::assertNull($status['url']);
    }
}
