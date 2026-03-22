<?php

declare(strict_types=1);

namespace Tests\Commands;

use Laragear\Expose\Commands\ListTunnelsCommand;
use Laragear\Expose\Enums\TunnelService;
use Laragear\Expose\Tunnels\AbstractTunnel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

/** Tests ListTunnelsCommand table output and filtering. */
class ListTunnelsCommandTest extends CommandTestCase
{
    protected function makeCommand(): Command
    {
        return new ListTunnelsCommand();
    }

    public function test_exits_successfully(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir);

            static::assertSame(Command::SUCCESS, $this->runAndGetTester()->getStatusCode());
        });
    }

    public function test_output_contains_all_built_in_service_keys(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir);

            $output = $this->runAndGetTester([], ['decorated' => false])->getDisplay();

            foreach (TunnelService::cases() as $service) {
                static::assertStringContainsString($service->value, $output);
            }
        });
    }

    public function test_output_marks_active_tunnel(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'ngrok']);

            $output = $this->runAndGetTester([], ['decorated' => false])->getDisplay();

            // The active marker check symbol should appear somewhere in the ngrok row.
            static::assertStringContainsString('YES', $output);
        });
    }

    public function test_output_labels_built_ins_as_built_in(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir);

            $output = $this->runAndGetTester([], ['decorated' => false])->getDisplay();

            static::assertStringContainsString('built-in', $output);
        });
    }

    public function test_custom_tunnel_appears_with_custom_label(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, [
                'tunnels' => [
                    'fake-ext' => [
                        'label' => 'Fake Extension Tunnel',
                        'class' => FakeExtTunnel::class,
                    ],
                ],
            ]);

            $output = $this->runAndGetTester([], ['decorated' => false])->getDisplay();

            static::assertStringContainsString('fake-ext', $output);
            static::assertStringContainsString('Fake Extension Tunnel', $output);
            static::assertStringContainsString('custom', $output);
        });
    }

    public function test_installed_filter_hides_uninstalled_services(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir);

            // All built-in binaries are almost certainly absent in CI.
            // With --installed, the table should be empty or print only SSH-based Pinggy if ssh exists.
            $tester = $this->runAndGetTester(['--installed' => true], ['decorated' => false]);
            $output = $tester->getDisplay();

            // At minimum, none of the binary-based services should appear.
            static::assertStringNotContainsString('ngrok', $output);
            static::assertStringNotContainsString('cloudflared', $output);
            static::assertStringNotContainsString('zrok', $output);
        });
    }

    public function test_note_shown_when_no_tunnel_configured(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir);

            $output = $this->runAndGetTester([], ['decorated' => false])->getDisplay();

            static::assertStringContainsString('No tunnel configured', $output);
        });
    }

    public function test_note_shows_active_tunnel_when_configured(): void
    {
        $this->withTempDir(function (string $dir): void {
            chdir($dir);
            $this->seedComposerJson($dir, ['tunnel' => 'zrok']);

            $output = $this->runAndGetTester([], ['decorated' => false])->getDisplay();

            static::assertStringContainsString('Active tunnel: <info>zrok</info>', $output);
        });
    }
}

/** Minimal concrete tunnel used only in list command tests. */
class FakeExtTunnel extends AbstractTunnel
{
    public function name(): string
    {
        return 'Fake Extension Tunnel';
    }

    public function binary(): string
    {
        return 'fake-ext';
    }

    public function start(string $host = 'localhost', int $port = 8080): Process
    {
        return $this->buildProcess(['echo', 'fake']);
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'test-tunnel';
    }
}
