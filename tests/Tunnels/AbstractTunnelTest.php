<?php

declare(strict_types=1);

namespace Tests\Tunnels;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\Http;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Tunnels\AbstractTunnel;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Tests the shared behaviour provided by AbstractTunnel. */
class AbstractTunnelTest extends TestCase
{
    /** Returns a minimal concrete subclass of AbstractTunnel for testing. */
    protected function makeTunnel(array $overrides = []): AbstractTunnel
    {
        return new class($overrides) extends AbstractTunnel {
            public function __construct(private readonly array $overrides = [])
            {
                //
            }

            public function name(): string
            {
                return $this->overrides['name'] ?? 'TestTunnel';
            }

            public function binary(): string
            {
                return $this->overrides['binary'] ?? 'testtunnel';
            }

            public function start(ProcessFactory $factory, string $host = 'localhost', int $port = 8080): Process
            {
                return $this->buildProcess('echo', 'started');
            }

            /**
             * @inheritDoc
             */
            public function label(): string
            {
                return 'testing tunnel';
            }
        };
    }

    public function test_default_is_not_npm_package(): void
    {
        static::assertFalse($this->makeTunnel()->isInstallableViaNpm());
    }

    public function test_default_npm_package_name_is_null(): void
    {
        static::assertNull($this->makeTunnel()->npmPackageName());
    }

    public function test_default_download_url_is_null(): void
    {
        static::assertNull($this->makeTunnel()->downloadUrl());
    }

    public function test_default_configurable_options_is_empty(): void
    {
        static::assertSame([], $this->makeTunnel()->configurableOptions());
    }

    public function test_default_status_returns_not_running(): void
    {
        $status = $this->makeTunnel()->status();

        static::assertFalse($status['running']);
        static::assertNull($status['url']);
        static::assertNull($status['connections']);
    }

    /** Returns an npm-based concrete tunnel for testing npm paths. */
    protected function makeNpmTunnel(): AbstractTunnel
    {
        return new class($this->mock(Http::class), $this->mock(BinaryManager::class), $this->mock(ProcessFactory::class)) extends AbstractTunnel {
            protected bool $npmPackage = true;
            protected ?string $npmPackageName = 'my-package';

            public function name(): string
            {
                return 'NpmTunnel';
            }

            public function binary(): string
            {
                return 'npmtunnel';
            }

            public function start(ProcessFactory $factory, string $host = 'localhost', int $port = 8080): Process
            {
                return $this->buildProcess('echo', 'started');
            }

            /**
             * @inheritDoc
             */
            public function label(): string
            {
                return 'testing label';
            }
        };
    }

    public function test_npm_tunnel_reports_as_npm_package(): void
    {
        static::assertTrue($this->makeNpmTunnel()->isInstallableViaNpm());
    }

    public function test_npm_tunnel_returns_package_name(): void
    {
        static::assertSame('my-package', $this->makeNpmTunnel()->npmPackageName());
    }
}
