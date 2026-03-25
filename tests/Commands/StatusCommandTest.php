<?php

namespace Tests\Commands;

use Laragear\Expose\Commands\StatusCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\TunnelRegistry;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\TestCase;

class StatusCommandTest extends TestCase
{
    protected StatusCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new StatusCommand();
    }

    public function test_command_configuration(): void
    {
        static::assertSame('expose:status', $this->command->getName());
        static::assertSame(
            'Show the current status of the configured tunnel service.', $this->command->getDescription()
        );

        static::assertCount(1, $this->command->getDefinition()->getOptions());

        $optionTunnel = $this->command->getDefinition()->getOption('tunnel');

        static::assertSame('tunnel', $optionTunnel->getName());
        static::assertSame('t', $optionTunnel->getShortcut());
        static::assertNull($optionTunnel->getDefault());
        static::assertSame('Override which tunnel to check.', $optionTunnel->getDescription());
    }

    public function test_fails_when_no_tunnel_is_available(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturnNull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tunnel service configured. Run `composer expose` first');

        static::assertSame(StatusCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_fails_when_tunnel_is_not_installable(): void
    {
        $this->mock(ComposerConfig::class, static function (MockInterface $mock): void {
            $mock->expects('get')->with('tunnel')->andReturn('test-tunnel');
        });

        $tunnel = $this->mock(Tunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->twice()->andReturn('test-tunnel-name');
        });

        $this->mock(TunnelRegistry::class, static function (MockInterface $mock) use ($tunnel): void {
            $mock->expects('make')->with('test-tunnel')->andReturn($tunnel);
        });

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with("test-tunnel-name Status");
            $mock->expects('error')->with("test-tunnel-name has no logic for installation.");
        });

        static::assertSame(StatusCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_fails_when_tunnel_is_not_installed(): void
    {
        $this->mock(ComposerConfig::class, static function (MockInterface $mock): void {
            $mock->expects('get')->with('tunnel')->andReturn('test-tunnel');
        });

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->twice()->andReturn('test-tunnel-name');
            $mock->expects('isInstalled')->andReturnFalse();
        });

        $this->mock(TunnelRegistry::class, static function (MockInterface $mock) use ($tunnel): void {
            $mock->expects('make')->with('test-tunnel')->andReturn($tunnel);
        });

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with("test-tunnel-name Status");
            $mock->expects('error')->with("test-tunnel-name is not installed.");
        });

        static::assertSame(StatusCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public static function providesTunnelStatus(): array
    {
        return [
            [true, 'https://test-tunnel.com', 2],
            [false, null, null],
        ];
    }

    #[DataProvider('providesTunnelStatus')]
    public function test_shows_tunnel_status(bool $isRunning, ?string $url, ?int $connections): void
    {
        $this->mock(ComposerConfig::class, static function (MockInterface $mock): void {
            $mock->expects('get')->with('tunnel')->andReturn('test-tunnel');
        });

        $tunnel = $this->mock(
            InstallableTunnel::class,
            static function (MockInterface $mock) use ($isRunning, $url, $connections): void {
                $mock->expects('name')->andReturn('test-tunnel-name');
                $mock->expects('isInstalled')->andReturnTrue();
                $mock->expects('status')->andReturn([
                    'running' => $isRunning,
                    'url' => $url,
                    'connections' => $connections,
                ]);
            }
        );

        $this->mock(TunnelRegistry::class, static function (MockInterface $mock) use ($tunnel): void {
            $mock->expects('make')->with('test-tunnel')->andReturn($tunnel);
        });

        $this->mock(
            SymfonyStyle::class, static function (MockInterface $mock) use ($isRunning, $url, $connections): void {
                $mock->expects('title')->with("test-tunnel-name Status");
                $mock->expects('definitionList')->with(
                    ['Status' => $isRunning ? '<info>Running</info>' : '<comment>Not running</comment>'],
                    ['Public URL' => $url ?? '-'],
                    ['Connections' => $connections !== null ? (string) $connections : '-'],
                );
            }
        );

        static::assertSame(StatusCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }
}
