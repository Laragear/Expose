<?php

declare(strict_types=1);

namespace Laragear\Expose\Contracts;

use Laragear\Expose\Support\ProcessFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

interface Tunnel
{
    /**
     * Returns the unique machine-readable name of the tunnel service.
     */
    public function name(): string;

    /**
     * Returns the human-readable display label.
     */
    public function label(): string;

    /**
     * Persists a single configuration value for this tunnel service.
     *
     * @param  array<string, \Laragear\Expose\Support\Option>  $values
     */
    public function configure(SymfonyStyle $io, array $values): void;

    /**
     * Returns all configurable options for this tunnel service.
     *
     * @return array<string, \Laragear\Expose\Support\Option>
     */
    public function configurableOptions(): array;

    /**
     * Starts the tunnel pointing at the given local host and port, returning the live process.
     */
    public function start(ProcessFactory $factory, string $host = 'localhost', int $port = 8080): Process;

    /**
     * Finds the published address from the tunnel process output.
     *
     * This method may be called several times waiting for the process to set up
     * the tunnel and output messages to find the published address there.
     */
    public function publishedAddress(Process $tunnelProcess): ?string;

    /**
     * Returns the current status of the tunnel as an associative array.
     *
     * @return array{running: bool, url: ?string, connections: int|null, error?: ?string}
     */
    public function status(): array;
}
