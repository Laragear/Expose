<?php

declare(strict_types=1);

namespace Tests\Commands;

use Laragear\Expose\Commands\StatusCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

/** Tests StatusCommand output and edge cases. */
class StatusCommandTest extends CommandTestCase
{
    protected function makeCommand(): Command
    {
        return new StatusCommand();
    }

    public function test_throws_when_no_tunnel_configured(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/No tunnel service configured/');

            $this->runCommand();
        });
    }

    public function test_exits_successfully_when_tunnel_configured_but_not_installed(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'ngrok']);

            // ngrok is almost certainly not installed in CI — status should still succeed
            $tester = $this->runAndGetTester();

            static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        });
    }

    public function test_override_tunnel_option_is_respected(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            // composer.json says ngrok but --tunnel=zrok should override
            $this->seedComposerJson($dir, ['tunnel' => 'ngrok']);

            $tester = $this->runAndGetTester(['--tunnel' => 'zrok']);

            static::assertSame(Command::SUCCESS, $tester->getStatusCode());
            static::assertStringContainsString('Zrok', $tester->getDisplay());
        });
    }

    public function test_throws_for_unknown_tunnel_override(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'ngrok']);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Unknown tunnel service/');

            $this->runCommand(['--tunnel' => 'nonexistent-service']);
        });
    }

    public function test_status_output_contains_status_heading(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'cloudflare']);

            $tester = $this->runAndGetTester([], ['decorated' => false]);

            static::assertStringContainsStringIgnoringCase('status', $tester->getDisplay());
        });
    }
}
