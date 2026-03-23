<?php

declare(strict_types=1);

namespace Tests\Support;

use JsonException;
use Laragear\Expose\Support\ComposerConfig;
use RuntimeException;
use Tests\TestCase;

/** Tests reading from and writing to the extra.expose block in composer.json. */
class ComposerConfigTest extends TestCase
{
    /** Returns a minimal composer.json string with no extra block. */
    private function minimalComposerJson(): string
    {
        return json_encode(['name' => 'test/app', 'require' => new \stdClass()], JSON_PRETTY_PRINT);
    }

    /** Returns a composer.json string pre-seeded with expose config. */
    private function seededComposerJson(array $expose): string
    {
        return json_encode(
            ['name' => 'test/app', 'extra' => ['expose' => $expose]],
            JSON_PRETTY_PRINT
        );
    }

    public function test_throws_when_composer_json_is_missing(): void
    {
        $composer = new ComposerConfig('/nonexistent/path/composer.json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/composer\.json not found/');

        $composer->all();
    }

    public function test_throws_when_composer_json_is_invalid_json(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', '/NOT JSON/');

            $composer = new ComposerConfig($dir . '/composer.json');

            $this->expectException(JsonException::class);

            $composer->all();
        });
    }

    public function test_get_returns_default_when_key_is_absent(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', $this->minimalComposerJson());

            $config = new ComposerConfig($dir . '/composer.json');

            static::assertNull($config->get('tunnel'));
            static::assertSame('fallback', $config->get('tunnel', 'fallback'));
        });
    }

    public function test_get_returns_saved_value(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', $this->seededComposerJson(['tunnel' => 'ngrok']));

            $config = new ComposerConfig($dir . '/composer.json');

            static::assertSame('ngrok', $config->get('tunnel'));
        });
    }

    public function test_set_persists_value_to_disk(): void
    {
        $this->withTempDir(function (string $dir): void {
            $path = $dir . '/composer.json';
            $this->writeFile($dir, 'composer.json', $this->minimalComposerJson());

            $config = new ComposerConfig($path);
            $config->set('tunnel', 'cloudflare');

            // Re-read from disk to confirm persistence
            $reloaded = new ComposerConfig($path);
            static::assertSame('cloudflare', $reloaded->get('tunnel'));
        });
    }

    public function test_forget_removes_key_from_disk(): void
    {
        $this->withTempDir(function (string $dir): void {
            $path = $dir . '/composer.json';
            $this->writeFile($dir, 'composer.json', $this->seededComposerJson(['tunnel' => 'ngrok']));

            $config = new ComposerConfig($path);
            $config->forget('tunnel');

            $reloaded = new ComposerConfig($path);
            static::assertNull($reloaded->get('tunnel'));
        });
    }

    public function test_all_returns_entire_expose_block(): void
    {
        $expose = ['tunnel' => 'ngrok', 'options' => ['region' => 'eu']];

        $this->withTempDir(function (string $dir) use ($expose): void {
            $this->writeFile($dir, 'composer.json', $this->seededComposerJson($expose));

            $config = new ComposerConfig($dir . '/composer.json');

            static::assertSame($expose, $config->all());
        });
    }

    public function test_all_returns_empty_array_when_no_expose_block(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', $this->minimalComposerJson());

            $config = new ComposerConfig($dir . '/composer.json');

            static::assertSame([], $config->all());
        });
    }

    public function test_set_preserves_existing_composer_json_content(): void
    {
        $this->withTempDir(function (string $dir): void {
            $path = $dir . '/composer.json';
            $original = json_encode([
                'name'    => 'test/app',
                'require' => ['php' => '^8.3'],
            ], JSON_PRETTY_PRINT);

            $this->writeFile($dir, 'composer.json', $original);

            $config = new ComposerConfig($path);
            $config->set('tunnel', 'pinggy');

            $decoded = json_decode(file_get_contents($path), true);

            // Non-expose keys must remain untouched
            static::assertSame('test/app', $decoded['name']);
            static::assertSame('^8.3', $decoded['require']['php']);
            static::assertSame('pinggy', $decoded['extra']['expose']['tunnel']);
        });
    }
}
