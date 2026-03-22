<?php

declare(strict_types=1);

namespace Tests\Commands;

use Laragear\Expose\Commands\UpdateCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;

/** Tests UpdateCommand resolution and error handling. */
class UpdateCommandTest extends CommandTestCase
{
    protected function makeCommand(): Command
    {
        return new UpdateCommand();
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

    public function test_throws_for_unknown_tunnel_value(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'unknown-service']);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Unknown tunnel service/');

            $this->runCommand();
        });
    }

    public function test_returns_failure_when_tunnel_not_installed(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            // Use a service that is very unlikely to be installed in CI
            $this->seedComposerJson($dir, ['tunnel' => 'zrok']);

            $tester = $this->runAndGetTester();

            static::assertSame(Command::FAILURE, $tester->getStatusCode());
            static::assertStringContainsString('not appear to be installed', $tester->getDisplay());
        });
    }

    public function test_tunnel_option_overrides_composer_json(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'ngrok']);

            // Cloudflare is also not installed in CI
            $tester = $this->runAndGetTester(['--tunnel' => 'cloudflare']);

            // Should fail with "not installed", not "unknown service"
            static::assertSame(Command::FAILURE, $tester->getStatusCode());
            static::assertStringContainsStringIgnoringCase('cloudflare', $tester->getDisplay());
        });
    }
}
