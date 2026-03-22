<?php

namespace Laragear\Expose\Contracts;

use Laragear\Expose\Support\BinaryManager;
use Symfony\Component\Console\Style\SymfonyStyle;

interface InstallableTunnel extends Tunnel
{
    /**
     * Installs the tunnel binaries.
     *
     * @return bool "true" if it was installed, "false" if was already installed.
     */
    public function install(BinaryManager $manager, SymfonyStyle $io): bool;

    /**
     * Checks whether the tunnel binary is available on the system.
     */
    public function isInstalled(): bool;

    /**
     * Checks whether the tunnel is distributed via NPM rather than a direct download.
     */
    public function isInstallableViaNpm(): bool;

    /**
     * Returns the NPM package name for NPM-based tunnels, or null for binary-based ones.
     */
    public function npmPackageName(): ?string;

    /**
     * Returns the download URL for the tunnel binaries, if any.
     */
    public function downloadUrl(): ?string;

    /**
     * Updates the tunnel binary to the latest version, optionally forcing a full reinstall.
     */
    public function update(SymfonyStyle $io, bool $force = false): void;

    /**
     * Uninstalls the tunnel binary from the system.
     */
    public function uninstall(SymfonyStyle $io): void;
}
