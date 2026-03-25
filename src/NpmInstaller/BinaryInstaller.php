<?php

namespace Laragear\Expose\NpmInstaller;

use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Support\BinaryManager;
use Symfony\Component\Console\Style\SymfonyStyle;

class BinaryInstaller
{
    /**
     * Create a new Npm Installers instance.
     */
    public function __construct(protected SymfonyStyle $io, protected BinaryManager $manager)
    {
        //
    }

    /**
     * Install the tunnel required binaries.
     *
     * @param  \Laragear\Expose\Contracts\InstallableTunnel  $tunnel
     * @return bool
     */
    public function install(InstallableTunnel $tunnel): bool
    {
        return $tunnel->install($this->manager, $this->io);
    }
}
