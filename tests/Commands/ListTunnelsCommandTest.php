<?php

namespace Tests\Commands;

use Laragear\Expose\Commands\ListTunnelsCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Enums\TunnelService;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\TunnelRegistry;
use Laragear\Expose\Tunnels\AbstractTunnel;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\TestCase;

class ListTunnelsCommandTest extends TestCase
{
    protected ListTunnelsCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new ListTunnelsCommand();
    }

    public function test_command_configuration(): void
    {
        static::assertSame('expose:list', $this->command->getName());
        static::assertSame(
            'List all available tunnel services, including custom and package-provided ones.',
            $this->command->getDescription(),
        );

        static::assertCount(1, $this->command->getDefinition()->getOptions());

        $optionTunnel = $this->command->getDefinition()->getOption('installed');

        static::assertSame('installed', $optionTunnel->getName());
        static::assertSame('i', $optionTunnel->getShortcut());
        static::assertFalse($optionTunnel->getDefault());
        static::assertSame('Show only services whose binary is currently installed.', $optionTunnel->getDescription());
    }

    public function test_list_all_tunnels(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('bar');

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Available Tunnel Services');
            $mock->expects('table')->with(['Key', 'Label', 'Type', 'Binary', 'Installed', 'Active'], [
                ['foo', 'foo', '<fg=cyan>custom</>', 'unknown binary', '<comment>no</comment>', ''],
                ['bar', 'bar', '<fg=cyan>custom</>', 'bar-binary', '<info>yes</info>', '<info>YES</info>'],
                ['baz', 'baz', '<fg=cyan>custom</>', 'unknown binary', '<comment>no</comment>', ''],
                ['cloudflare', 'cloudflare', 'built-in', 'unknown binary', '<comment>no</comment>', ''],
            ]);
            $mock->expects('note')->with(
                'Active tunnel: <info>bar</info>. Change it with: composer expose:configure --reset',
            );
        });

        $foo = Mockery::mock(InstallableTunnel::class);
        $foo->expects('isInstalled')->andReturnFalse();
        $foo->expects('name')->andReturn('foo');
        $bar = Mockery::mock(AbstractTunnel::class);
        $bar->expects('isInstalled')->andReturnTrue();
        $bar->expects('binary')->andReturn('bar-binary');
        $bar->expects('name')->andReturn('bar');
        $baz = Mockery::mock(Tunnel::class);
        $baz->expects('name')->andReturn('baz');
        $qux = Mockery::mock(InstallableTunnel::class);
        $qux->expects('isInstalled')->andReturnFalse();
        $qux->expects('name')->andReturn(TunnelService::Cloudflare->value);

        $this->mock(TunnelRegistry::class, static function (MockInterface $mock) use ($qux, $baz, $bar, $foo): void {
            $mock->expects('keys')->andReturn(['foo', 'bar', 'baz', TunnelService::Cloudflare->value]);

            $mock->expects('make')->with('foo')->andReturn($foo);
            $mock->expects('make')->with('bar')->andReturn($bar);
            $mock->expects('make')->with('baz')->andReturn($baz);
            $mock->expects('make')->with(TunnelService::Cloudflare->value)->andReturn($qux);
        });

        static::assertSame(ListTunnelsCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_lists_no_tunnels_if_empty(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('bar');

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Available Tunnel Services');
            $mock->expects('note')->with(
                'No tunnel services found. Try removing the --installed filter.',
            );
        });

        $this->mock(TunnelRegistry::class)->expects('keys')->andReturn([]);

        static::assertSame(ListTunnelsCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_shows_no_active_tunnel(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturnNull();

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Available Tunnel Services');
            $mock->expects('table')->withAnyArgs();
            $mock->expects('note')->with(
                'Active tunnel: <info>bar</info>. Change it with: composer expose:configure --reset',
            )->never();
            $mock->expects('note')->with('No tunnel configured yet. Run `composer expose` to choose one.');
        });

        $foo = Mockery::mock(InstallableTunnel::class);
        $foo->expects('isInstalled')->andReturnFalse();
        $foo->expects('name')->andReturn('foo');

        $this->mock(TunnelRegistry::class, static function (MockInterface $mock) use ($foo): void {
            $mock->expects('keys')->andReturn(['foo']);
            $mock->expects('make')->with('foo')->andReturn($foo);
        });

        static::assertSame(ListTunnelsCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_list_only_installed(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('bar');

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Available Tunnel Services');
            $mock->expects('table')->with(['Key', 'Label', 'Type', 'Binary', 'Installed', 'Active'], [
                ['bar', 'bar', '<fg=cyan>custom</>', 'bar-binary', '<info>yes</info>', '<info>YES</info>'],
            ]);
            $mock->expects('note')->with(
                'Active tunnel: <info>bar</info>. Change it with: composer expose:configure --reset',
            );
        });

        $foo = Mockery::mock(InstallableTunnel::class);
        $foo->expects('isInstalled')->andReturnFalse();
        $bar = Mockery::mock(AbstractTunnel::class);
        $bar->expects('isInstalled')->andReturnTrue();
        $bar->expects('binary')->andReturn('bar-binary');
        $bar->expects('name')->andReturn('bar');
        $baz = Mockery::mock(Tunnel::class);
        $qux = Mockery::mock(InstallableTunnel::class);
        $qux->expects('isInstalled')->andReturnFalse();

        $this->mock(TunnelRegistry::class, static function (MockInterface $mock) use ($qux, $baz, $bar, $foo): void {
            $mock->expects('keys')->andReturn(['foo', 'bar', 'baz', TunnelService::Cloudflare->value]);

            $mock->expects('make')->with('foo')->andReturn($foo);
            $mock->expects('make')->with('bar')->andReturn($bar);
            $mock->expects('make')->with('baz')->andReturn($baz);
            $mock->expects('make')->with(TunnelService::Cloudflare->value)->andReturn($qux);
        });

        static::assertSame(
            ListTunnelsCommand::SUCCESS,
            $this->command->run(new ArrayInput(['--installed' => true]), new NullOutput())
        );
    }
}
