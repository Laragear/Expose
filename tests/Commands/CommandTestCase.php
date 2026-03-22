<?php

declare(strict_types=1);

namespace Tests\Commands;

use Composer\Console\Application;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

/**
 * Base for command tests: registers a command into a throwaway Application and exposes a tester.
 */
abstract class CommandTestCase extends TestCase
{
    /**
     * Returns the command instance to test.
     */
    abstract protected function makeCommand(): Command;

    /**
     * Registers the command inside a minimal Application and returns a CommandTester.
     */
    protected function testerFor(Command $command): CommandTester
    {
        $app = new Application('Expose Test', '0.0.1');
        $app->setAutoExit(false);
        $app->addCommand($command);

        return new CommandTester($command);
    }

    /**
     * Runs the command with the given input array and returns its exit code.
     */
    protected function runCommand(array $input = [], array $options = []): int
    {
        return $this->testerFor($this->makeCommand())->execute($input, $options);
    }

    /**
     * Runs the command and returns the CommandTester for further assertions.
     */
    protected function runAndGetTester(array $input = [], array $options = []): CommandTester
    {
        $tester = $this->testerFor($this->makeCommand());

        if (isset($options['inputs'])) {
            $tester->setInputs($options['inputs']);
            unset($options['inputs']);
        }

        $tester->execute($input, $options);

        return $tester;
    }

    /**
     * Builds a minimal composer.json at the given path with optional extra.expose content.
     *
     * @param array<string, mixed> $expose
     */
    protected function seedComposerJson(string $dir, array $expose = []): string
    {
        $data = ['name' => 'test/app', 'require' => new stdClass()];

        if ($expose !== []) {
            $data['extra']['expose'] = $expose;
        }

        $path = $dir . '/composer.json';
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        return $path;
    }
}
