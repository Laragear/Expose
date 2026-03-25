<?php

declare(strict_types=1);

namespace Tests\Support;

use Laragear\Expose\Enums\Framework;
use Laragear\Expose\Support\File;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Support\ServerRunner;
use Mockery\MockInterface;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ServerRunnerTest extends TestCase
{
    protected File&MockInterface $file;
    protected ProcessFactory&MockInterface $process;
    protected ServerRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = $this->mock(File::class);
        $this->process = $this->mock(ProcessFactory::class);
        $this->runner = new ServerRunner($this->file, $this->process, '/app');
    }

    public function test_starts_framework_with_builtin_server(): void
    {
        $this->expectNotToPerformAssertions();

        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('setTimeout')->with(null);
            $mock->expects('start');
        });

        $this->process->expects('command')
            ->with('php', 'artisan', 'serve', '--host=test-hostname', '--port=1234')
            ->andReturnSelf();
        $this->process->expects('process')->andReturn($process);

        $this->runner->start(Framework::Laravel, 'test-hostname', 1234);
    }

    public function test_starts_project_with_php_server(): void
    {
        $this->expectNotToPerformAssertions();

        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('setTimeout')->with(null);
            $mock->expects('start');
        });

        $this->file->expects('findOnPath')->with('php')->andReturnNull();
        $this->process->expects('command')->with('php', '-S', 'test-hostname:1234', '-t', '/app/.')->andReturnSelf();
        $this->process->expects('workDir')->with('/app')->andReturnSelf();
        $this->process->expects('process')->andReturn($process);

        $this->runner->start(Framework::Unknown, 'test-hostname', 1234);
    }
}
