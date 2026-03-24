<?php

namespace Laragear\Expose\Support;

use BadMethodCallException;
use Symfony\Component\Process\Process;
use function array_filter;
use function array_map;
use function in_array;
use function php_uname;
use function strtolower;
use const PHP_OS_FAMILY;

/**
 * @mixin \Symfony\Component\Process\Process
 */
class ProcessFactory
{
    /**
     * Create a new Process Factory instance.
     */
    public function __construct(
        protected string $command = '',
        protected string $workingDirectory = '',
        protected array $arguments = [],
        protected int $timeout = 0,
        protected bool $muteMessages = false,
        protected bool $muteErrors = false,
        protected ?Process $process = null,
    ) {
        //
    }

    /**
     * Returns the current OS.
     */
    public function os(): string
    {
        return PHP_OS_FAMILY;
    }

    /**
     * Check if the current OS is Windows.
     */
    public function isWindows(): bool
    {
        return $this->os() === 'Windows';
    }

    /**
     * Check if the current OS is Unix.
     */
    public function isUnix(): bool
    {
        return $this->os() === 'Linux' || $this->os() === 'Darwin';
    }

    /**
     * Returns the architecture of the current os.
     */
    public function arch(): string
    {
        return php_uname('m');
    }

    /**
     * Check if the current architecture is x86.
     */
    public function isX86(): bool
    {
        return in_array(strtolower($this->arch()), ['x86', 'i386', 'i686', 'amd64', 'x86-64', 'x64'], true);
    }

    /**
     * Check if the current architecture is ARM.
     */
    public function isArm(): bool
    {
        $arch = strtolower($this->arch());

        return (str_contains($arch, 'arm') || str_contains($arch, 'aarch'));
    }

    /**
     * Call and execute a given command.
     */
    public function call(string $command): Process
    {
        return (new static($command))->run();
    }

    /**
     * Creates a Process instance as a command-line to be run in a shell wrapper.
     */
    public function fromShellCommandLine(string $command, string $root): Process
    {
        return Process::fromShellCommandline($command, $root);
    }

    /**
     * Builds a process.
     *
     * @return $this
     */
    public function command(string $command, string ...$rawArgs): static
    {
        $this->command = $command;

        $this->workingDirectory = (string) getcwd();

        return $this->raw(...$rawArgs);
    }

    /**
     * Sets the working directory.
     *
     * @return $this
     */
    public function workDir(string $workDir): static
    {
        $this->workingDirectory = $workDir;

        return $this;
    }

    /**
     * Sets raw arguments to pass to the command.
     */
    public function raw(string ...$arguments): static
    {
        $this->arguments = [...$this->arguments, ...$arguments];

        return $this;
    }

    /**
     * Sets the arguments to pass escaped to the command.
     *
     * @return $this
     */
    public function args(string ...$arguments): static
    {
        return $this->raw(...array_map(escapeshellarg(...), $arguments));
    }

    /**
     * Mutes errors (standard error).
     *
     * @return $this
     */
    public function muteErrors(): static
    {
        $this->muteErrors = true;

        return $this;
    }

    /**
     * Mutes messages (standard output).
     *
     * @return $this
     */
    public function muteMessages(): static
    {
        $this->muteMessages = true;

        return $this;
    }

    /**
     * Mutes the command.
     */
    public function mute(): static
    {
        return $this->muteErrors()->muteMessages();
    }

    /**
     * Creates a process instance.
     */
    public function process(): Process
    {
        if (isset($this->process)) {
            return $this->process;
        }

        $command = [
            $this->command,
            ...array_map(escapeshellarg(...), $this->arguments)
        ];

        $command []= match (true) {
            $this->muteMessages && $this->muteErrors => $this->isWindows() ? '> NUL 2>&1' : '> /dev/null 2>&1',
            $this->muteMessages => $this->isWindows() ? '> NUL' : '> /dev/null',
            $this->muteErrors => $this->isWindows() ? '2> NUL' : '2> /dev/null',
            default => '',
        };

        return $this->process = new Process(array_filter($command), $this->workingDirectory);
    }

    /**
     * Runs the command.
     */
    public function run(): Process
    {
        $process = $this->process();

        $process->run();

        return $process;
    }

    /**
     * Handle dynamic calls to the process result
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (method_exists(Process::class, $name)) {
            $result = $this->process()->$name(...$arguments);

            return $result instanceof Process ? $this : $result;
        }

        throw new BadMethodCallException('Method [' . static::class . "::$name] does not exist.");
    }
}
