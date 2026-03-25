<?php

namespace Tests\Commands;

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

class UpdateCommandTest extends TestCase
{
    protected UpdateCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new UpdateCommand();
    }

    public function test_command_configuration(): void
    {
        static::assertSame('expose:update', $this->command->getName());
        static::assertSame('Update the configured tunnel service binary.', $this->command->getDescription());

        static::assertCount(2, $this->command->getDefinition()->getOptions());

        $optionTunnel = $this->command->getDefinition()->getOption('force');

        static::assertSame('force', $optionTunnel->getName());
        static::assertSame('f', $optionTunnel->getShortcut());
        static::assertFalse($optionTunnel->getDefault());
        static::assertSame('Force re-download even if already up to date.', $optionTunnel->getDescription());

        $optionTunnel = $this->command->getDefinition()->getOption('tunnel');

        static::assertSame('tunnel', $optionTunnel->getName());
        static::assertSame('t', $optionTunnel->getShortcut());
        static::assertNull($optionTunnel->getDefault());
        static::assertSame('Override which tunnel to update.', $optionTunnel->getDescription());
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
            $mock->expects('error')->with('test-name is not updateable. You have to update it manually.');
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-key')->andReturn($tunnel);

        static::assertSame(UpdateCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
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

        static::assertSame(UpdateCommand::FAILURE, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_updates_tunnel(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-key');

        $io = $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Updating test-name...');
            $mock->expects('success')->with('test-name is up to date.');
        });

        $binary = $this->mock(BinaryManager::class);

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock) use ($binary, $io): void {
            $mock->expects('name')->twice()->andReturn('test-name');
            $mock->expects('isInstalled')->andReturnTrue();
            $mock->expects('update')->with($binary, $io, false);
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-key')->andReturn($tunnel);

        static::assertSame(UpdateCommand::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_forcefully_updates_tunnel(): void
    {
        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-key');

        $io = $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Updating test-name...');
            $mock->expects('success')->with('test-name is up to date.');
        });

        $binary = $this->mock(BinaryManager::class);

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock) use ($binary, $io): void {
            $mock->expects('name')->twice()->andReturn('test-name');
            $mock->expects('isInstalled')->andReturnTrue();
            $mock->expects('update')->with($binary, $io, true);
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-key')->andReturn($tunnel);

        static::assertSame(
            UpdateCommand::SUCCESS, $this->command->run(new ArrayInput(['--force' => true]), new NullOutput())
        );
    }
}
