<?php

declare(strict_types=1);

namespace Tests\Support;

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;
use Laragear\Expose\Support\ComposerConfig;
use Mockery\MockInterface;
use Tests\TestCase;

class ComposerConfigTest extends TestCase
{
    protected JsonConfigSource&MockInterface $source;
    protected JsonFile&MockInterface $file;
    protected ComposerConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = $this->mock(JsonConfigSource::class);
        $this->file = $this->mock(JsonFile::class);
        $this->composer = new ComposerConfig($this->source, $this->file);
    }

    public function test_all(): void
    {
        $this->file->expects('read')->andReturn(['extra' => ['foo' => 'bar']]);

        static::assertSame(['foo' => 'bar'], $this->composer->all());
    }

    public function test_get(): void
    {
        $this->file->expects('read')->times(4)->andReturn(['extra' => ['foo' => 'bar', 'baz' => ['quz' => 'qux']]]);

        static::assertSame(['foo' => 'bar', 'baz' => ['quz' => 'qux']], $this->composer->get(''));
        static::assertSame(['quz' => 'qux'], $this->composer->get('baz'));
        static::assertSame('bar', $this->composer->get('foo'));
        static::assertSame('qux', $this->composer->get('baz.quz'));
    }

    public function test_set(): void
    {
        $this->expectNotToPerformAssertions();

        $this->source->expects('addProperty')->with('extra.foo', 'baz');

        $this->composer->set('foo', 'baz');
    }

    public function test_forget(): void
    {
        $this->expectNotToPerformAssertions();

        $this->source->expects('removeProperty')->with('extra.foo');

        $this->composer->forget('foo');
    }
}
