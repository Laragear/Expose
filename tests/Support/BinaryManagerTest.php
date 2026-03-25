<?php

declare(strict_types=1);

namespace Tests\Support;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\File;
use Laragear\Expose\Support\ProcessFactory;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use function decoct;
use const DIRECTORY_SEPARATOR as DS;

class BinaryManagerTest extends TestCase
{
    protected File&MockInterface $file;
    protected ProcessFactory&MockInterface $process;
    protected BinaryManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = $this->mock(File::class);
        $this->process = $this->mock(ProcessFactory::class);
        $this->manager = new BinaryManager($this->file, $this->process, '/app');
    }

    public function test_bin_dir_returns_correct_path(): void
    {
        static::assertSame(
            '/app'.DS.'.expose'.DS.'bin',
            $this->manager->binariesDir,
        );
    }

    public function test_bin_path_appends_exe_on_windows(): void
    {
        $this->process->expects('isWindows')->andReturnTrue();

        static::assertStringEndsWith('.exe', $this->manager->binPath('ngrok'));
    }

    public function test_bin_path_has_no_extension_on_unix(): void
    {
        $this->process->expects('isWindows')->andReturnFalse();

        static::assertStringEndsWith('ngrok', $this->manager->binPath('ngrok'));
    }

    public function test_checks_if_npm_is_available(): void
    {
        $this->file->expects('findOnPath')->with('npm')->andReturn('/app/npm');
        $this->file->expects('findOnPath')->with('npm')->andReturnNull();

        static::assertTrue($this->manager->isNpmAvailable());
        static::assertFalse($this->manager->isNpmAvailable());
    }

    public function test_is_installed_returns_true_when_binary_exists_locally(): void
    {
        $this->file->expects('findOnPath')->with('fakebinary')->andReturn('/usr/bin/path');

        static::assertTrue($this->manager->isInstalled('fakebinary'));
    }

    public function test_is_installed_returns_false_when_binary_absent(): void
    {
        $this->process->expects('isWindows')->andReturnFalse();

        $this->file->expects('findOnPath')->with('fakebinary')->andReturnNull();
        $this->file
            ->expects('exists')
            ->with($this->manager->binariesDir.DS.'fakebinary')
            ->andReturnFalse();

        static::assertFalse($this->manager->isInstalled('fakebinary'));
    }

    public function test_downloads_via_curl_fails(): void
    {
        $this->file->expects('isNotDir')->andReturnFalse();
        $this->process->expects('isWindows')->andReturnFalse();

        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('setTimeout')->with(120)->andReturnSelf();
            $mock->expects('run');
            $mock->expects('isSuccessful')->andReturnFalse();
            $mock->expects('getErrorOutput')->andReturn('test-error-output');
        });

        $this->process->expects('command')
            ->with('curl', '-fsSl', '-o', '/app/.expose/bin/test-binary', 'https://www.test-url.com')
            ->andReturnSelf();
        $this->process->expects('process')->andReturn($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to download [test-binary] from [https://www.test-url.com]: test-error-output');

        $this->manager->downloadViaCurl('https://www.test-url.com', 'test-binary');
    }

    public function test_downloads_via_curl(): void
    {
        $this->expectNotToPerformAssertions();

        $this->file->expects('isNotDir')->andReturnFalse();
        $this->file->expects('chmod')->with('/app/.expose/bin/test-binary', 0755);
        $this->process->expects('isWindows')->andReturnFalse();

        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('setTimeout')->with(120)->andReturnSelf();
            $mock->expects('run');
            $mock->expects('isSuccessful')->andReturnTrue();
        });

        $this->process->expects('command')
            ->with('curl', '-fsSl', '-o', '/app/.expose/bin/test-binary', 'https://www.test-url.com')
            ->andReturnSelf();
        $this->process->expects('process')->andReturn($process);
        $this->process->expects('isUnix')->andReturnTrue();

        $this->manager->downloadViaCurl('https://www.test-url.com', 'test-binary');
    }

    public function test_installs_via_npm_fails_when_not_successful(): void
    {
        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('setTimeout')->with(120)->andReturnSelf();
            $mock->expects('run');
            $mock->expects('isSuccessful')->andReturnFalse();
            $mock->expects('getErrorOutput')->andReturn('test-error-output');
        });

        $this->process->expects('command')->with('npm', 'install', '-g', 'test-package')->andReturnSelf();
        $this->process->expects('process')->andReturn($process);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to install [test-package] via NPM: test-error-output');

        $this->manager->installViaNpm('test-package');
    }

    public function test_installs_via_npm(): void
    {
        $this->expectNotToPerformAssertions();

        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('setTimeout')->with(120)->andReturnSelf();
            $mock->expects('run');
            $mock->expects('isSuccessful')->andReturnTrue();
        });

        $this->process->expects('command')->with('npm', 'install', '-g', 'test-package')->andReturnSelf();
        $this->process->expects('process')->andReturn($process);

        $this->manager->installViaNpm('test-package');
    }

    public function test_uninstalls_via_npm(): void
    {
        $this->expectNotToPerformAssertions();

        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('setTimeout')->with(120)->andReturnSelf();
            $mock->expects('run');
        });

        $this->process->expects('command')->with('npm', 'uninstall', '-g', 'test-package')->andReturnSelf();
        $this->process->expects('process')->andReturn($process);

        $this->manager->uninstallViaNpm('test-package');
    }

    public function test_remove_binary_deletes_local_file(): void
    {
        $this->expectNotToPerformAssertions();

        $this->process->expects('isWindows')->times(3)->andReturnFalse();

        $this->file->expects('exists')->with($this->manager->binPath('fakebinary'))->andReturnTrue();
        $this->file->expects('delete')->with($this->manager->binPath('fakebinary'));

        $this->manager->removeBinary('fakebinary');
    }

    public function test_remove_binary_is_noop_when_file_absent(): void
    {
        $this->expectNotToPerformAssertions();

        $this->process->expects('isWindows')->twice()->andReturnFalse();

        $this->file->expects('exists')->with($this->manager->binPath('fakebinary'))->andReturnFalse();
        $this->file->expects('delete')->never();

        $this->manager->removeBinary('fakebinary');
    }

    public function test_resolve_command_returns_local_path_when_binary_exists(): void
    {
        $this->file->expects('exists')->with('/app/.expose/bin/fakebinary')->andReturnTrue();
        $this->process->expects('isWindows')->twice()->andReturnFalse();

        $binPath = $this->manager->binPath('fakebinary');

        static::assertSame($binPath, $this->manager->resolveCommand('fakebinary'));
    }

    public function test_resolve_command_returns_binary_name_when_no_local_copy(): void
    {
        $this->file->expects('exists')->with('/app/.expose/bin/ngrok')->andReturnFalse();
        $this->process->expects('isWindows')->andReturnFalse();

        static::assertSame('ngrok', $this->manager->resolveCommand('ngrok'));
    }
}
