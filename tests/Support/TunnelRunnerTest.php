<?php

namespace Tests\Support;

use Laragear\Expose\Contracts\Tunnel;
use Laragear\Expose\Support\Date;
use Laragear\Expose\Support\ProcessFactory;
use Laragear\Expose\Support\TunnelRunner;
use Mockery\MockInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TunnelRunnerTest extends TestCase
{
    protected SymfonyStyle&MockInterface $io;
    protected ProcessFactory&MockInterface $factory;
    protected Date&MockInterface $date;
    protected Tunnel&MockInterface $tunnel;

    protected TunnelRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->io = $this->mock(SymfonyStyle::class);
        $this->factory = $this->mock(ProcessFactory::class);
        $this->date = $this->mock(Date::class);
        $this->tunnel = $this->mock(Tunnel::class);

        $this->runner = new TunnelRunner($this->io, $this->factory, $this->date);
    }

    public function test_start_without_auto_detected_url(): void
    {
        $this->expectNotToPerformAssertions();

        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('isRunning')->andReturnTrue();
        });

        $this->date->expects('now')->with(15)->andReturn(115);
        $this->date->expects('now')->withNoArgs()->andReturn(100);
        $this->date->expects('sleep')->once();
        $this->date->expects('now')->withNoArgs()->andReturn(115);

        $this->tunnel->expects('name')->andReturn('test-tunnel');
        $this->tunnel->expects('start')->with($this->factory, 'localhost', 8080)->andReturn($process);
        $this->tunnel->expects('publishedAddress')->with($process)->andReturnNull();

        $this->io->expects('text')->with('Starting <info>test-tunnel</info> tunnel...');
        $this->io->expects('note')->with('Could not auto-detect the public URL in 15 seconds. Check tunnel output above.');

        $this->runner->start($this->tunnel, 'localhost', 8080);
    }

    public function test_start_with_auto_detected_url(): void
    {
        $this->expectNotToPerformAssertions();

        $process = $this->mock(Process::class, static function (MockInterface $mock): void {
            $mock->expects('isRunning')->andReturnTrue();
        });

        $this->date->expects('now')->with(15)->andReturn(115);
        $this->date->expects('now')->withNoArgs()->andReturn(100);

        $this->tunnel->expects('name')->andReturn('test-tunnel');
        $this->tunnel->expects('start')->with($this->factory, 'localhost', 8080)->andReturn($process);
        $this->tunnel->expects('publishedAddress')->with($process)->andReturn('https://tunnel-test.com');

        $this->io->expects('text')->with('Starting <info>test-tunnel</info> tunnel...');
        $this->io->expects('success')->with('Public URL: https://tunnel-test.com');

        $this->runner->start($this->tunnel, 'localhost', 8080);
    }
}
