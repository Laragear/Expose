<?php

declare(strict_types=1);

namespace Tests\Commands;

use Laragear\Expose\Commands\UninstallCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use const DIRECTORY_SEPARATOR;

/** Tests UninstallCommand guard rails, confirmation prompts, and purge behaviour. */
class UninstallCommandTest extends CommandTestCase
{
    protected function makeCommand(): Command
    {
        return new UninstallCommand();
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

    public function test_skips_gracefully_when_tunnel_not_installed(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'zrok']);

            // zrok not installed → note and exit success
            $tester = $this->runAndGetTester([], ['decorated' => false]);

            static::assertSame(Command::SUCCESS, $tester->getStatusCode());
            static::assertStringContainsString('not appear to be installed', $tester->getDisplay());
        });
    }

    public function test_tunnel_override_option_is_respected(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'ngrok']);

            $tester = $this->runAndGetTester(['--tunnel' => 'zrok'], ['decorated' => false]);

            // Should mention zrok, not ngrok
            static::assertStringContainsStringIgnoringCase('zrok', $tester->getDisplay());
        });
    }

    public function test_purge_removes_expose_block_from_composer_json(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);

            // Plant a locally managed fake binary so the tunnel appears "installed"
            $binPath = $dir.DIRECTORY_SEPARATOR.'.expose'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'zrok';
            mkdir(dirname($binPath), 0755, true);
            file_put_contents($binPath, '#!/bin/sh');
            chmod($binPath, 0755);

            $this->seedComposerJson($dir, ['tunnel' => 'zrok', 'options' => ['share_mode' => 'public']]);

            $this->runAndGetTester(
                ['--tunnel' => 'zrok', '--purge' => true],
                ['decorated' => false, 'inputs' => ['yes']],   // confirm the prompt
            );

            $decoded = json_decode(file_get_contents($dir.DIRECTORY_SEPARATOR.'composer.json'), true);

            static::assertEmpty($decoded['extra']['expose']);
        });
    }
}
