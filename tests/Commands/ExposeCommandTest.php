<?php

declare(strict_types=1);

namespace Tests\Commands;

use Closure;
use Laragear\Expose\Commands\ExposeCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Detectors\ProjectDetector;
use Laragear\Expose\Enums\Framework;
use Laragear\Expose\NpmInstaller\BinaryInstaller;
use Laragear\Expose\NpmInstaller\NpmInstaller;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\ServerRunner;
use Laragear\Expose\Support\TunnelRegistry;
use Laragear\Expose\Support\TunnelRunner;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Tests ExposeCommand helper methods in isolation via reflection and mocks.
 */
class ExposeCommandTest extends TestCase
{
    protected ExposeCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new ExposeCommand();
    }

    public function test_command_configuration(): void
    {
        static::assertSame('expose', $this->command->getName());
        static::assertSame(
            'Expose your local PHP project to the internet via a tunnel service.', $this->command->getDescription()
        );

        static::assertCount(3, $this->command->getDefinition()->getOptions());

        $optionTunnel = $this->command->getDefinition()->getOption('host');

        static::assertSame('host', $optionTunnel->getName());
        static::assertNull($optionTunnel->getShortcut());
        static::assertSame('localhost', $optionTunnel->getDefault());
        static::assertSame('The local host to serve from.', $optionTunnel->getDescription());

        $optionTunnel = $this->command->getDefinition()->getOption('port');

        static::assertSame('port', $optionTunnel->getName());
        static::assertSame('p', $optionTunnel->getShortcut());
        static::assertSame(8080, $optionTunnel->getDefault());
        static::assertSame('The local port to serve from.', $optionTunnel->getDescription());

        $optionTunnel = $this->command->getDefinition()->getOption('tunnel');

        static::assertSame('tunnel', $optionTunnel->getName());
        static::assertSame('t', $optionTunnel->getShortcut());
        static::assertNull($optionTunnel->getDefault());
        static::assertSame('Override the tunnel service for this run.', $optionTunnel->getDescription());
    }

    public function test_bypasses_not_installable_tunnel(): void
    {
        $this->mock(ProjectDetector::class)->expects('detect')->andReturn(Framework::Unknown);

        $tunnelProcess = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('getOutput')->zeroOrMoreTimes()->andReturn('https://test.tunnel.com/');
            $mock->expects('getErrorOutput')->zeroOrMoreTimes()->andReturn('');
            $mock->expects('stop');
        });

        ($tunnel = $this->mock(Tunnel::class))->expects('name')->twice()->andReturn('test-tunnel');

        $this->mock(TunnelRunner::class)->expects('start')->with($tunnel, 'localhost', '8080')->andReturn($tunnelProcess);

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-tunnel')->andReturn($tunnel);

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('info')->with('Tunnel [test-tunnel] may not be installed.');
            $mock->expects('section')->with('Starting PHP project on http://localhost:8080');
            $mock->expects('text')->with('Local server started. Waiting for tunnel...');
            $mock->expects('text')->with('Started <info>test-tunnel</info> tunnel.');
            $mock->expects('newLine');
            $mock->expects('text')->with('Detected project: <info>PHP</info>');
            $mock->expects('text')->with('<comment>Press Ctrl+C to stop the tunnel and server.</comment>');
            $mock->expects('success')->with('Tunnel and server stopped.');
        });

        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-tunnel');

        $serverProcess = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('stop');
            $mock->expects('isRunning')->andReturnFalse();
        });

        $this->mock(ServerRunner::class)->expects('start')->with(Framework::Unknown, 'localhost', '8080')->andReturn($serverProcess);

        static::assertSame(ExposeCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_bypasses_already_installed_tunnel(): void
    {
        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('section')->with('Starting PHP project on http://localhost:8080');
            $mock->expects('text')->with('Local server started. Waiting for tunnel...');
            $mock->expects('text')->with('Started <info>test-tunnel</info> tunnel.');
            $mock->expects('newLine');
            $mock->expects('text')->with('Detected project: <info>PHP</info>');
            $mock->expects('text')->with('<comment>Press Ctrl+C to stop the tunnel and server.</comment>');
            $mock->expects('success')->with('Tunnel and server stopped.');
        });

        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-tunnel');

        $serverProcess = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('stop');
            $mock->expects('isRunning')->andReturnFalse();
        });

        $this->mock(ServerRunner::class)->expects('start')->with(Framework::Unknown, 'localhost', '8080')->andReturn($serverProcess);

        $this->mock(ProjectDetector::class)->expects('detect')->andReturn(Framework::Unknown);

        $tunnelProcess = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('getOutput')->zeroOrMoreTimes()->andReturn('https://test.tunnel.com/');
            $mock->expects('getErrorOutput')->zeroOrMoreTimes()->andReturn('');
            $mock->expects('stop');
        });

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock) use ($tunnelProcess): void {
            $mock->expects('isInstalled')->andReturnTrue();
            $mock->expects('name')->once()->andReturn('test-tunnel');
        });

        $this->mock(TunnelRunner::class)->expects('start')->with($tunnel, 'localhost', '8080')->andReturn($tunnelProcess);

        $this->mock(TunnelRegistry::class, static function (MockInterface $mock) use ($tunnel): void {
            $mock->expects('make')->with('test-tunnel')->andReturn($tunnel);
        });

        static::assertSame(ExposeCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public static function providesFailedInstalledTunnel(): array
    {
        return [
            [
                function () {
                    $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
                        $mock->expects('isInstallableViaNpm')->andReturnTrue();
                    });
                    $this->mock(NpmInstaller::class)->expects('install')->with($tunnel)->andReturnFalse();
                }
            ],
            [
                function () {
                    $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
                        $mock->expects('isInstallableViaNpm')->andReturnFalse();
                    });
                    $this->mock(BinaryInstaller::class)->expects('install')->with($tunnel)->andReturnFalse();
                }
            ]
        ];
    }

    #[DataProvider('providesFailedInstalledTunnel')]
    public function test_installs_tunnel_via_npm_but_fails_if_installer_returns_false(Closure $mock): void
    {
        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('info')->with('Tunnel [test-tunnel] may not be installed.')->never();
            $mock->expects('section')->with('Starting PHP project on http://localhost:8080')->never();
            $mock->expects('text')->with('Detected project: <info>PHP</info>');
        });

        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-tunnel');

        $this->mock(ProjectDetector::class)->expects('detect')->andReturn(Framework::Unknown);

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock) : void {
            $mock->expects('isInstalled')->andReturnFalse();
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-tunnel')->andReturn($tunnel);

        $mock->call($this);

        static::assertSame(ExposeCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public static function providesInstalledTunnel(): array
    {
        return [
            [
                function () {
                    $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
                        $mock->expects('isInstallableViaNpm')->andReturnTrue();
                    });
                    $this->mock(NpmInstaller::class)->expects('install')->with($tunnel)->andReturnTrue();
                }
            ],
            [
                function () {
                    $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
                        $mock->expects('isInstallableViaNpm')->andReturnFalse();
                    });
                    $this->mock(BinaryInstaller::class)->expects('install')->with($tunnel)->andReturnTrue();
                }
            ]
        ];
    }

    #[DataProvider('providesInstalledTunnel')]
    public function test_install_and_exposes_after_installing_with_npm(Closure $mock): void
    {
        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('section')->with('Starting PHP project on http://localhost:8080');
            $mock->expects('text')->with('Local server started. Waiting for tunnel...');
            $mock->expects('text')->with('Started <info>test-tunnel</info> tunnel.');
            $mock->expects('newLine');
            $mock->expects('text')->with('Detected project: <info>PHP</info>');
            $mock->expects('text')->with('<comment>Press Ctrl+C to stop the tunnel and server.</comment>');
            $mock->expects('success')->with('Tunnel and server stopped.');
        });

        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-tunnel');

        $serverProcess = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('stop');
            $mock->expects('isRunning')->andReturnFalse();
        });

        $this->mock(ServerRunner::class)->expects('start')->with(Framework::Unknown, 'localhost', '8080')->andReturn($serverProcess);

        $this->mock(ProjectDetector::class)->expects('detect')->andReturn(Framework::Unknown);

        $tunnelProcess = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('getOutput')->zeroOrMoreTimes()->andReturn('https://test.tunnel.com/');
            $mock->expects('getErrorOutput')->zeroOrMoreTimes()->andReturn('');
            $mock->expects('stop');
        });

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock) use ($tunnelProcess): void {
            $mock->expects('isInstalled')->andReturnFalse();
            $mock->expects('name')->once()->andReturn('test-tunnel');
        });

        $this->mock(TunnelRunner::class)->expects('start')->with($tunnel, 'localhost', '8080')->andReturn($tunnelProcess);

        $this->mock(TunnelRegistry::class, static function (MockInterface $mock) use ($tunnel): void {
            $mock->expects('make')->with('test-tunnel')->andReturn($tunnel);
        });

        $mock->call($this);

        static::assertSame(ExposeCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }
}
