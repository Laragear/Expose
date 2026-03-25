<?php

namespace Tests\Commands;

use Laragear\Expose\Commands\UninstallCommand;
use Laragear\Expose\Commands\UpdateCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\TunnelRegistry;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\TestCase;

class UninstallCommandTest extends TestCase
{
    protected UninstallCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new UninstallCommand();
    }

    public function test_command_configuration(): void
    {
        static::assertSame('expose:uninstall', $this->command->getName());
        static::assertSame('Uninstall the tunnel service binary.', $this->command->getDescription());

        static::assertCount(2, $this->command->getDefinition()->getOptions());

        $optionTunnel = $this->command->getDefinition()->getOption('tunnel');

        static::assertSame('tunnel', $optionTunnel->getName());
        static::assertSame('t', $optionTunnel->getShortcut());
        static::assertNull($optionTunnel->getDefault());
        static::assertSame('Override which tunnel to uninstall.', $optionTunnel->getDescription());

        $optionTunnel = $this->command->getDefinition()->getOption('purge');

        static::assertSame('purge', $optionTunnel->getName());
        static::assertNull($optionTunnel->getShortcut());
        static::assertFalse($optionTunnel->getDefault());
        static::assertSame('Also remove all Expose config from composer.json.', $optionTunnel->getDescription());
    }

    public function test_fails_without_tunnel(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturnNull();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No tunnel service configured. Run `composer expose` first.');

        $this->command->run(new ArrayInput([]), new NullOutput());
    }

    public function test_fails_if_tunnel_not_installable(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-key');

        $tunnel = $this->mock(Tunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->andReturn('test-name');
        });

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('error')->with('test-name is not uninstallable. You have to remove it manually.');
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-key')->andReturn($tunnel);

        static::assertSame(UninstallCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_fails_if_tunnel_is_not_installed(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-key');

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->andReturn('test-name');
            $mock->expects('isInstalled')->andReturnFalse();
        });

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('note')->with('test-name does not appear to be installed.');
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-key')->andReturn($tunnel);

        static::assertSame(UninstallCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_fails_if_tunnel_uninstallation_is_not_confirmed(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-key');

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->andReturn('test-name');
            $mock->expects('isInstalled')->andReturnTrue();
        });

        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('confirm')->with('Are you sure you want to uninstall test-name?', false)->andReturnFalse();
            $mock->expects('error')->with('Uninstall cancelled.');
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-key')->andReturn($tunnel);

        static::assertSame(UninstallCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_uninstalls_tunnel(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-key');

        $io = $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('confirm')->with('Are you sure you want to uninstall test-name?', false)->andReturnTrue();
            $mock->expects('title')->with('Uninstalling test-name...');
            $mock->expects('success')->with('test-name has been uninstalled.');
        });

        $this->mock(BinaryManager::class);

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock) use ($io): void {
            $mock->expects('name')->times(3)->andReturn('test-name');
            $mock->expects('isInstalled')->andReturnTrue();
            $mock->expects('uninstall')->with(Mockery::type(BinaryManager::class), $io);
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-key')->andReturn($tunnel);

        static::assertSame(UninstallCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_uninstall_with_purge(): void
    {
        $this->mock(ComposerConfig::class, static function (MockInterface $mock): void {
            $mock->expects('get')->with('tunnel')->andReturn('test-key');
            $mock->expects('all')->andReturn(['foo' => 'first', 'bar' => 'second']);
            $mock->expects('forget')->with('foo');
            $mock->expects('forget')->with('bar');
        });

        $io = $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('confirm')->with('Are you sure you want to uninstall test-name?', false)->andReturnTrue();
            $mock->expects('title')->with('Uninstalling test-name...');
            $mock->expects('success')->with('test-name has been uninstalled.');
            $mock->expects('text')->with('Expose configuration removed from <comment>composer.json</comment>.');
        });

        $this->mock(BinaryManager::class);

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock) use ($io): void {
            $mock->expects('name')->times(3)->andReturn('test-name');
            $mock->expects('isInstalled')->andReturnTrue();
            $mock->expects('uninstall')->with(Mockery::type(BinaryManager::class), $io);
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-key')->andReturn($tunnel);

        static::assertSame(
            UninstallCommand::SUCCESS, $this->command->run(new ArrayInput(['--purge' => true]), new NullOutput())
        );
    }
}
