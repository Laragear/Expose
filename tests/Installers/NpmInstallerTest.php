<?php

namespace Tests\Installers;

use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\NpmInstaller\NpmInstaller;
use Laragear\Expose\Support\BinaryManager;
use Mockery\MockInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\TestCase;

class NpmInstallerTest extends TestCase
{
    protected BinaryManager&MockInterface $manager;
    protected SymfonyStyle&MockInterface $io;
    protected InstallableTunnel&MockInterface $tunnel;

    protected NpmInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->mock(BinaryManager::class);
        $this->io = $this->mock(SymfonyStyle::class);
        $this->tunnel = $this->mock(InstallableTunnel::class, static function (MockInterface $mock): void {
            $mock->expects('name')->zeroOrMoreTimes()->andReturn('test-name');
        });

        $this->installer = new NpmInstaller($this->io, $this->manager);
    }

    public function test_fails_to_install_without_package_name(): void
    {
        $this->tunnel->expects('npmPackageName')->andReturnNull();

        $this->io->expects('error')->with('The package name for the tunnel [test-name] is missing.');

        static::assertFalse($this->installer->install($this->tunnel));
    }

    public function test_fails_to_install_without_npm(): void
    {
        $this->tunnel->expects('npmPackageName')->andReturn('test-package');
        $this->manager->expects('isNpmAvailable')->andReturnFalse();

        $this->io->expects('error')->with('NPM is not installed. Please install Node.js and NPM first, then run: npm install -g test-package.');

        static::assertFalse($this->installer->install($this->tunnel));
    }

    public function test_fails_to_installs_because_it_wants_manual_installation(): void
    {
        $this->tunnel->expects('npmPackageName')->andReturn('test-package');
        $this->manager->expects('isNpmAvailable')->andReturnTrue();

        $this->io->expects('choice')->with('What would you like to do?', [
            'install' => "Install `test-package` via NPM now",
            'manual' => 'I will install it manually and retry later',
        ])->andReturn('manual');
        $this->io->expects('text')->with('Run this command, then try again: <comment>npm install -g test-package</comment>');

        static::assertFalse($this->installer->install($this->tunnel));
    }

    public function test_installs_via_npm(): void
    {
        $this->tunnel->expects('npmPackageName')->twice()->andReturn('test-package');
        $this->manager->expects('isNpmAvailable')->andReturnTrue();

        $this->io->expects('choice')->with('What would you like to do?', [
            'install' => "Install `test-package` via NPM now",
            'manual' => 'I will install it manually and retry later',
        ])->andReturn('install');

        $this->manager->expects('installViaNpm')->with('test-package');

        $this->io->expects('success')->with('test-name installed.');

        static::assertTrue($this->installer->install($this->tunnel));
    }
}
