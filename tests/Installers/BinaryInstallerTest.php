<?php

namespace Tests\Installers;

use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\NpmInstaller\BinaryInstaller;
use Laragear\Expose\Support\BinaryManager;
use Mockery\MockInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\TestCase;

class BinaryInstallerTest extends TestCase
{
    protected BinaryManager&MockInterface $manager;
    protected SymfonyStyle&MockInterface $io;
    protected InstallableTunnel&MockInterface $tunnel;

    protected BinaryInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->mock(BinaryManager::class);
        $this->io = $this->mock(SymfonyStyle::class);
        $this->tunnel = $this->mock(InstallableTunnel::class);

        $this->installer = new BinaryInstaller($this->io, $this->manager);
    }

    public function test_installs_tunnel(): void
    {
        $this->tunnel->expects('install')->with($this->manager, $this->io)->andReturnTrue();

        static::assertTrue($this->installer->install($this->tunnel));
    }
}
