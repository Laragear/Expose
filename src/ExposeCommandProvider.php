<?php

declare(strict_types=1);

namespace Laragear\Expose;

use Composer\Plugin\Capability\CommandProvider;
use Laragear\Expose\Commands\ConfigureCommand;
use Laragear\Expose\Commands\ExposeCommand;
use Laragear\Expose\Commands\ListTunnelsCommand;
use Laragear\Expose\Commands\StatusCommand;
use Laragear\Expose\Commands\UninstallCommand;
use Laragear\Expose\Commands\UpdateCommand;

/**
 * Provides all Expose commands to the Composer CLI.
 */
class ExposeCommandProvider implements CommandProvider
{
    /**
     * Retrieves an array of commands
     *
     * @return \Composer\Command\BaseCommand[]
     */
    public function getCommands(): array
    {
        return [
            new ExposeCommand(),
            new UpdateCommand(),
            new ConfigureCommand(),
            new StatusCommand(),
            new UninstallCommand(),
            new ListTunnelsCommand(),
        ];
    }
}
