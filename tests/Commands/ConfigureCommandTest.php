<?php

namespace Tests\Commands;

use Laragear\Expose\Commands\ConfigureCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\ComposerConfig;
use Laragear\Expose\Support\Option;
use Laragear\Expose\Support\TunnelRegistry;
use Mockery as m;
use Mockery\MockInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\TestCase;

class ConfigureCommandTest extends TestCase
{
    protected ConfigureCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new ConfigureCommand();
    }

    public function test_command_configuration(): void
    {
        static::assertSame('expose:configure', $this->command->getName());
        static::assertSame(
            'Configure credentials and options for the tunnel service.', $this->command->getDescription()
        );

        static::assertCount(2, $this->command->getDefinition()->getOptions());

        $optionTunnel = $this->command->getDefinition()->getOption('tunnel');

        static::assertSame('tunnel', $optionTunnel->getName());
        static::assertSame('t', $optionTunnel->getShortcut());
        static::assertNull($optionTunnel->getDefault());
        static::assertSame('Override which tunnel to configure.', $optionTunnel->getDescription());

        $optionTunnel = $this->command->getDefinition()->getOption('reset');

        static::assertSame('reset', $optionTunnel->getName());
        static::assertNull($optionTunnel->getShortcut());
        static::assertFalse($optionTunnel->getDefault());
        static::assertSame('Reset the preferred tunnel choice stored in composer.json.', $optionTunnel->getDescription());
    }

    public function test_resets_tunnel(): void
    {
        $this->mock(SymfonyStyle::class)
            ->expects('success')
            ->with('Tunnel preference reset. Run `composer expose` to choose again.');

        $this->mock(ComposerConfig::class)->expects('forget')->with('tunnel');

        static::assertSame(
            Command::SUCCESS, $this->command->run(new ArrayInput(['--reset' => true]), new NullOutput())
        );
    }

    public function test_configures_non_installable_tunnel(): void
    {
        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Configuring test-tunnel');
            $mock->expects('warning')->with('test-tunnel does not appear to be installed. Configuration may not persist.');
            $mock->expects('success')->with('test-tunnel configured successfully.');
        });

        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-tunnel');

        $tunnel = $this->mock(Tunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->times(3)->andReturn('test-tunnel');
            $mock->expects('configurableOptions')->andReturn([
                'option' => Option::name('test-option')->optional()
            ]);
            $mock->expects('configure');
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-tunnel')->andReturn($tunnel);

        static::assertSame(Command::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_configures_installable_tunnel_installed(): void
    {
        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Configuring test-tunnel');
            $mock->expects('success')->with('test-tunnel configured successfully.');
        });

        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-tunnel');

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->times(2)->andReturn('test-tunnel');
            $mock->expects('configurableOptions')->andReturn([
                'option' => Option::name('test-option')->optional()
            ]);
            $mock->expects('configure');
            $mock->expects('isInstalled')->andReturnTrue();
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-tunnel')->andReturn($tunnel);

        static::assertSame(Command::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));
    }

    public function test_configures_installable_tunnel_not_installed(): void
    {
        $this->mock(SymfonyStyle::class, static function (MockInterface $mock): void {
            $mock->expects('title')->with('Configuring test-tunnel');
            $mock->expects('success')->with('test-tunnel configured successfully.');
            $mock->expects('warning')->with('test-tunnel does not appear to be installed. Configuration may not persist.');
        });

        $this->mock(ComposerConfig::class)->expects('get')->with('tunnel')->andReturn('test-tunnel');

        $tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->times(3)->andReturn('test-tunnel');
            $mock->expects('configurableOptions')->andReturn([
                'option' => Option::name('test-option')->optional()
            ]);
            $mock->expects('configure');
            $mock->expects('isInstalled')->andReturnFalse();
        });

        $this->mock(TunnelRegistry::class)->expects('make')->with('test-tunnel')->andReturn($tunnel);

        static::assertSame(Command::SUCCESS, $this->command->run(new ArrayInput([]), new NullOutput()));;
    }
}
