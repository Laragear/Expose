<?php

declare(strict_types=1);

namespace Tests\Commands;

use Laragear\Expose\Commands\ExposeCommand;
use Laragear\Expose\Contracts\InstallableTunnel;
use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Enums\Framework;
use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Tunnels\AbstractTunnel;
use stdClass;
use Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Tests ExposeCommand helper methods in isolation via reflection and mocks.
 */
class ExposeCommandTest extends TestCase
{
    /**
     * Returns an accessible ReflectionMethod for the given ExposeCommand method name.
     */
    private function method(string $name): ReflectionMethod
    {
        return new ReflectionMethod(ExposeCommand::class, $name);
    }

    /**
     * Builds a SymfonyStyle backed by a BufferedOutput for assertions.
     */
    private function makeIo(): SymfonyStyle
    {
        return new SymfonyStyle(new ArrayInput([]), new BufferedOutput());
    }

    // -----------------------------------------------------------------------
    // detectFramework
    // -----------------------------------------------------------------------

    public function test_detect_framework_identifies_laravel_from_artisan(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', json_encode(['require' => new stdClass()]));
            $this->writeFile($dir, 'artisan', '<?php');

            $result = $this->method('detectFramework')->invoke(new ExposeCommand(), $this->makeIo(), $dir);

            static::assertSame(Framework::Laravel, $result);
        });
    }

    public function test_detect_framework_falls_back_to_unknown(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', json_encode(['require' => []]));

            $result = $this->method('detectFramework')->invoke(new ExposeCommand(), $this->makeIo(), $dir);

            static::assertSame(Framework::Unknown, $result);
        });
    }

    // -----------------------------------------------------------------------
    // pollForUrl
    // -----------------------------------------------------------------------

    public function test_poll_for_url_extracts_ngrok_url(): void
    {
        /** @var \Symfony\Component\Process\Process&\PHPUnit\Framework\MockObject\MockObject $process */
        $process = $this->createMock(Process::class);
        $process->method('isRunning')->willReturn(true);
        $process->method('getOutput')->willReturn('url=https://abc123.ngrok.io obj=tunnels');
        $process->method('getErrorOutput')->willReturn('');

        $url = $this->method('pollForUrl')->invoke(new ExposeCommand(), $process);

        static::assertNotNull($url);
        static::assertStringContainsString('ngrok', (string) $url);
    }

    public function test_poll_for_url_extracts_trycloudflare_url(): void
    {
        /** @var Process&MockObject $process */
        $process = $this->createMock(Process::class);
        $process->method('isRunning')->willReturn(true);
        $process->method('getOutput')->willReturn('Visit: https://demo.trycloudflare.com');
        $process->method('getErrorOutput')->willReturn('');

        $url = $this->method('pollForUrl')->invoke(new ExposeCommand(), $process);

        static::assertNotNull($url);
        static::assertStringContainsString('trycloudflare', (string) $url);
    }

    public function test_poll_for_url_returns_null_when_nothing_matches(): void
    {
        /** @var Process&MockObject $process */
        $process = $this->createMock(Process::class);
        $process->method('isRunning')->willReturn(false);
        $process->method('getOutput')->willReturn('no url here');
        $process->method('getErrorOutput')->willReturn('');

        static::assertNull($this->method('pollForUrl')->invoke(new ExposeCommand(), $process));
    }

    // -----------------------------------------------------------------------
    // checkBinaryInstalled
    // -----------------------------------------------------------------------

    public function test_check_binary_installed_returns_true_when_already_installed(): void
    {
        $this->withTempDir(function (string $dir): void {
            /** @var Tunnel&MockObject $tunnel */
            $tunnel = $this->createMock(InstallableTunnel::class);
            $tunnel->method('isInstalled')->willReturn(true);

            $result = $this->method('checkBinaryInstalled')
                ->invoke(new ExposeCommand(), $this->makeIo(), $tunnel, new BinaryManager($dir));

            static::assertTrue($result);
        });
    }

    public function test_handle_missing_npm_binary_returns_false_without_npm(): void
    {
        /** @var Tunnel&MockObject $tunnel */
        $tunnel = $this->createMock(InstallableTunnel::class);
        $tunnel->method('name')->willReturn('TestNpm');
        $tunnel->method('npmPackageName')->willReturn('test-npm-pkg');

        /** @var BinaryManager&MockObject $manager */
        $manager = $this->createMock(BinaryManager::class);
        $manager->method('isNpmAvailable')->willReturn(false);

        $result = $this->method('handleMissingNpmBinary')
            ->invoke(new ExposeCommand(), $this->makeIo(), $tunnel, $manager);

        static::assertFalse($result);
    }

    public function test_handle_missing_download_binary_returns_false_with_no_url(): void
    {
        /** @var Tunnel&MockObject $tunnel */
        $tunnel = new class extends AbstractTunnel
        {
            /**
             * @inheritDoc
             */
            public function name(): string
            {
                return 'TestBinary';
            }

            /**
             * @inheritDoc
             */
            public function label(): string
            {
                return 'test_binary';
            }

            /**
             * @inheritDoc
             */
            public function start(string $host = 'localhost', int $port = 8080): Process
            {
                return new Process([]);
            }

            public function binary(): string
            {
                return 'testbinary';
            }

            public function downloadUrl(): ?string
            {
                return null;
            }
        };

        $result = $this->method('handleMissingDownloadBinary')
            ->invoke(new ExposeCommand(), $this->makeIo(), $tunnel, new BinaryManager(sys_get_temp_dir()));

        static::assertFalse($result);
    }
}
