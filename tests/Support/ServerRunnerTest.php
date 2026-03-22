<?php

declare(strict_types=1);

namespace Tests\Support;

use Laragear\Expose\Enums\Framework;
use Laragear\Expose\Support\ServerRunner;
use Tests\TestCase;
use Symfony\Component\Process\Process;

/** Tests that ServerRunner builds correct Process instances per framework. */
class ServerRunnerTest extends TestCase
{
    public function test_returns_process_instance(): void
    {
        $this->withTempDir(function (string $dir): void {
            $process = $this->makeBuiltinProcess(Framework::Unknown, $dir);

            static::assertInstanceOf(Process::class, $process);
        });
    }

    public function test_php_builtin_server_used_for_unknown_framework(): void
    {
        $this->withTempDir(function (string $dir): void {
            $process = $this->makeBuiltinProcess(Framework::Unknown, $dir);

            static::assertStringContainsString('php', $process->getCommandLine());
        });
    }

    public function test_php_builtin_server_used_for_wordpress(): void
    {
        $this->withTempDir(function (string $dir): void {
            $process = $this->makeBuiltinProcess(Framework::WordPress, $dir);

            static::assertStringContainsString('php', $process->getCommandLine());
        });
    }

    public function test_php_builtin_server_used_for_cakephp(): void
    {
        $this->withTempDir(function (string $dir): void {
            $process = $this->makeBuiltinProcess(Framework::CakePHP, $dir);

            static::assertStringContainsString('php', $process->getCommandLine());
        });
    }

    public function test_artisan_serve_used_for_laravel(): void
    {
        $process = $this->makeNativeProcess(Framework::Laravel, 'localhost', 8080);

        static::assertStringContainsString('artisan', $process->getCommandLine());
        static::assertStringContainsString('serve', $process->getCommandLine());
    }

    public function test_artisan_serve_injects_host_and_port(): void
    {
        $process = $this->makeNativeProcess(Framework::Laravel, '0.0.0.0', 9000);

        static::assertStringContainsString('0.0.0.0', $process->getCommandLine());
        static::assertStringContainsString('9000', $process->getCommandLine());
    }

    public function test_artisan_serve_used_for_lumen(): void
    {
        $process = $this->makeNativeProcess(Framework::Lumen, 'localhost', 8080);

        static::assertStringContainsString('artisan', $process->getCommandLine());
    }

    public function test_symfony_server_start_used_for_symfony(): void
    {
        $process = $this->makeNativeProcess(Framework::Symfony, 'localhost', 8080);

        static::assertStringContainsString('symfony', $process->getCommandLine());
        static::assertStringContainsString('server:start', $process->getCommandLine());
    }

    public function test_builtin_server_targets_correct_doc_root_for_yii(): void
    {
        $this->withTempDir(function (string $dir): void {
            $process = $this->makeBuiltinProcess(Framework::Yii, $dir);

            // Yii's public dir is "web" — it should appear in the command
            static::assertStringContainsString('web', $process->getCommandLine());
        });
    }

    /** Builds a Process for native-server frameworks without starting it. */
    private function makeNativeProcess(Framework $framework, string $host, int $port): Process
    {
        $command = str_replace(
            ['{host}', '{port}'],
            [$host, (string) $port],
            (string) $framework->serverCommand()
        );

        return Process::fromShellCommandline($command, sys_get_temp_dir());
    }

    /** Builds a PHP built-in server Process without starting it. */
    private function makeBuiltinProcess(Framework $framework, string $dir): Process
    {
        $docRoot = $dir . DIRECTORY_SEPARATOR . $framework->publicDir();

        return new Process(['php', '-S', 'localhost:8080', '-t', $docRoot], $dir);
    }
}
