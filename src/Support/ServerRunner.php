<?php

declare(strict_types=1);

namespace Laragear\Expose\Support;

use Laragear\Expose\Enums\Framework;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Starts a local PHP development server appropriate for the detected framework. */
class ServerRunner
{
    public function __construct(
        /** The absolute path to the project root directory. */
        protected readonly string $projectRoot,
    ) {
    }

    /**
     * Starts and returns a non-blocking Process for the local development server.
     * Uses the framework's native server command when available, otherwise PHP's built-in server.
     */
    public function start(Framework $framework, string $host, int $port): Process
    {
        $process = $framework->hasBuiltInServer()
            ? $this->buildNativeServerProcess($framework, $host, $port)
            : $this->buildPhpBuiltinProcess($framework, $host, $port);

        $process->setTimeout(null);
        $process->start();

        return $process;
    }

    /** Builds a Process for the framework's own CLI dev-server command. */
    protected function buildNativeServerProcess(Framework $framework, string $host, int $port): Process
    {
        $command = str_replace(
            ['{host}', '{port}'],
            [$host, (string) $port],
            (string) $framework->serverCommand()
        );

        return Process::fromShellCommandline($command, $this->projectRoot);
    }

    /** Builds a Process using PHP's built-in web server targeting the framework's public directory. */
    protected function buildPhpBuiltinProcess(Framework $framework, string $host, int $port): Process
    {
        $php     = (new ExecutableFinder())->find('php') ?? 'php';
        $docRoot = $this->projectRoot . DIRECTORY_SEPARATOR . $framework->publicDir();

        return new Process(
            [$php, '-S', "{$host}:{$port}", '-t', $docRoot],
            $this->projectRoot,
        );
    }
}
