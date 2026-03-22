<?php

declare(strict_types=1);

namespace Tests\Commands;

use Laragear\Expose\Commands\ConfigureCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use const DIRECTORY_SEPARATOR;

/** Tests ConfigureCommand prompt handling, reset flag, and persistence. */
class ConfigureCommandTest extends CommandTestCase
{
    protected function makeCommand(): Command
    {
        return new ConfigureCommand();
    }

    public function test_reset_flag_removes_saved_tunnel(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'ngrok']);

            $tester = $this->runAndGetTester(['--reset' => true], ['decorated' => false]);

            static::assertSame(Command::SUCCESS, $tester->getStatusCode());
            static::assertStringContainsString('reset', strtolower($tester->getDisplay()));

            $decoded = json_decode(file_get_contents($dir . DIRECTORY_SEPARATOR.'composer.json'), true);

            static::assertArrayNotHasKey('tunnel', $decoded['extra']['expose'] ?? []);
        });
    }

    public function test_tunnel_option_selects_service_without_prompt(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir);

            // Provide an empty string for each option prompt so the command finishes
            $tester = $this->testerFor($this->makeCommand());
            $tester->setInputs(['', '', '']);  // authtoken, hostname, region

            $tester->execute(['--tunnel' => 'ngrok'], ['decorated' => false]);

            static::assertSame(Command::SUCCESS, $tester->getStatusCode());
            static::assertStringContainsStringIgnoringCase('ngrok', $tester->getDisplay());
        });
    }

    public function test_non_secret_values_are_persisted_to_composer_json(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir);

            $tester = $this->testerFor($this->makeCommand());
            // authtoken (secret) = blank, hostname = blank, region = eu
            $tester->setInputs(['', '', 'eu']);

            $tester->execute(['--tunnel' => 'ngrok'], ['decorated' => false]);

            $decoded = json_decode(file_get_contents($dir . DIRECTORY_SEPARATOR . 'composer.json'), true);

            static::assertSame('eu', $decoded['extra']['expose']['options']['region'] ?? null);
        });
    }

    public function test_unknown_tunnel_override_throws(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'ngrok']);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/Unknown tunnel service/');

            $this->runCommand(['--tunnel' => 'bad-service']);
        });
    }

    public function test_configure_succeeds_even_when_binary_not_installed(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'zrok']);

            // Provide blank answers for token and share_mode
            $tester = $this->testerFor($this->makeCommand());
            $tester->setInputs(['', '']);

            $tester->execute([], ['decorated' => false]);

            // Should warn but not fail
            static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        });
    }
}
