<?php

declare(strict_types=1);

namespace Laragear\Expose\NpmInstaller;

use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Support\BinaryManager;
use Symfony\Component\Console\Style\SymfonyStyle;

class NpmInstaller
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
        $package = $tunnel->npmPackageName();

        if (!$this->manager->isNpmAvailable()) {
            $this->io->error("NPM is not installed. Please install Node.js and NPM first, then run: npm install -g $package.");

            return false;
        }

        if ($this->wantsManualInstallation($package)) {
            $this->io->text("Run this command, then try again: <comment>npm install -g $package</comment>");

            return false;
        }

        return $this->installViaNpm($tunnel);
    }

    /**
     * Ask the user if he wants to manually install the package (instead of doing it automatically).
     */
    protected function wantsManualInstallation(string $package): bool
    {
        return $this->io->choice('What would you like to do?', [
            'install' => "Install `$package` via NPM now",
            'manual' => 'I will install it manually and retry later',
        ]) !== 'install';
    }

    /**
     * Installs the package via NPM through the binary manager.
     */
    protected function installViaNpm(InstallableTunnel $tunnel): true
    {
        $this->manager->installViaNpm($tunnel->npmPackageName());

        $this->io->success("{$tunnel->name()} installed.");

        return true;
    }
}
