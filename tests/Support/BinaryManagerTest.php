<?php

declare(strict_types=1);

namespace Tests\Support;

use Laragear\Expose\Support\BinaryManager;
use Laragear\Expose\Support\File;
use Laragear\Expose\Support\ProcessFactory;
use Mockery\MockInterface;
use Tests\TestCase;
use const DIRECTORY_SEPARATOR as DS;

class BinaryManagerTest extends TestCase
{
    protected File&MockInterface $file;
    protected ProcessFactory&MockInterface $process;

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
