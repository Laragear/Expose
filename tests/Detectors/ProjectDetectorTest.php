<?php

declare(strict_types=1);

namespace Tests\Detectors;

use Laragear\Expose\Detectors\ProjectDetector;
use Laragear\Expose\Enums\Framework;
use Laragear\Expose\Support\File;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use function file_get_contents;
use function json_encode;
use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

class ProjectDetectorTest extends TestCase
{
    protected File&MockInterface $file;
    protected ProjectDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = $this->mock(File::class);

        $this->detector = new ProjectDetector($this->file, '/app');
    }

    public static function composerPackageProvider(): array
    {
        return [
            'Laravel via composer.json' => ['composer-laravel.json', Framework::Laravel],
            'Lumen via composer.json' => ['composer-lumen.json', Framework::Lumen],
            'Symfony via composer.json' => ['composer-symfony.json', Framework::Symfony],
            'CakePHP via composer.json' => ['composer-cakephp.json', Framework::CakePHP],
            'Yii via composer.json' => ['composer-yii.json', Framework::Yii],
        ];
    }

    #[DataProvider('composerPackageProvider')]
    public function test_detects_framework_from_composer_json(string $fixture, Framework $expected): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(
            file_get_contents($this->fixturesPath($fixture)),
        );

        $framework = $this->detector->detect();

        static::assertSame($expected, $framework);
    }

    public function test_detects_laravel_from_env_file(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(json_encode(['require' => []]));
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            ->andReturn((new File())->lines($this->fixturesPath('/env-laravel')));

        $framework = $this->detector->detect();

        static::assertSame(Framework::Laravel, $framework);
    }

    public function test_detects_wordpress_from_env_file(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(json_encode(['require' => []]));
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            ->andReturn((new File())->lines($this->fixturesPath('/env-wordpress')));

        $framework = $this->detector->detect();

        static::assertSame(Framework::WordPress, $framework);
    }

    public function test_detects_wordpress_from_wp_config(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(json_encode(['require' => []]));
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)->andReturn([]);
        $this->file->expects('exists')->with('/app/wp-config.php')->andReturnTrue();

        $framework = $this->detector->detect();

        static::assertSame(Framework::WordPress, $framework);
    }

    public function test_detects_wordpress_from_wp_config_with_no_env(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(json_encode(['require' => []]));
        $this->file->expects('missing')->with('/app/.env')->andReturnTrue();
        $this->file->expects('exists')->with('/app/wp-config.php')->andReturnTrue();

        $framework = $this->detector->detect();

        static::assertSame(Framework::WordPress, $framework);
    }

    public function test_detects_wordpress_from_wp_blog_header(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(json_encode(['require' => []]));
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)->andReturn([]);
        $this->file->expects('exists')->with('/app/wp-config.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/wp-blog-header.php')->andReturnTrue();

        $framework = $this->detector->detect();

        static::assertSame(Framework::WordPress, $framework);
    }

    public function test_detects_laravel_from_artisan_file(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(json_encode(['require' => []]));
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)->andReturn([]);
        $this->file->expects('exists')->with('/app/wp-config.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/wp-blog-header.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/artisan')->andReturnTrue();

        $framework = $this->detector->detect();

        static::assertSame(Framework::Laravel, $framework);
    }

    public function test_detects_symfony_from_filesystem(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(json_encode(['require' => []]));
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)->andReturn([]);
        $this->file->expects('exists')->with('/app/wp-config.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/wp-blog-header.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/artisan')->andReturnFalse();
        $this->file->expects('exists')->with('/app/bin/console')->andReturnTrue();
        $this->file->expects('exists')->with('/app/config/bundles.php')->andReturnTrue();

        $framework = $this->detector->detect();

        static::assertSame(Framework::Symfony, $framework);
    }

    public function test_falls_back_to_unknown_when_nothing_matches(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn(json_encode(['require' => []]));
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)->andReturn([]);
        $this->file->expects('exists')->with('/app/wp-config.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/wp-blog-header.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/artisan')->andReturnFalse();
        $this->file->expects('exists')->with('/app/bin/console')->andReturnTrue();
        $this->file->expects('exists')->with('/app/config/bundles.php')->andReturnFalse();

        $framework = $this->detector->detect();

        static::assertSame(Framework::Unknown, $framework);
    }

    public function test_handles_missing_composer_json_gracefully(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnTrue();
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)->andReturn([]);
        $this->file->expects('exists')->with('/app/wp-config.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/wp-blog-header.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/artisan')->andReturnFalse();
        $this->file->expects('exists')->with('/app/bin/console')->andReturnTrue();
        $this->file->expects('exists')->with('/app/config/bundles.php')->andReturnFalse();

        $framework = $this->detector->detect();

        static::assertSame(Framework::Unknown, $framework);
    }

    public function test_handles_malformed_composer_json_gracefully(): void
    {
        $this->file->expects('missing')->with('/app/composer.json')->andReturnFalse();
        $this->file->expects('get')->with('/app/composer.json')->andReturn('INVALID-JSON');
        $this->file->expects('missing')->with('/app/.env')->andReturnFalse();
        $this->file->expects('lines')->with('/app/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)->andReturn([]);
        $this->file->expects('exists')->with('/app/wp-config.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/wp-blog-header.php')->andReturnFalse();
        $this->file->expects('exists')->with('/app/artisan')->andReturnFalse();
        $this->file->expects('exists')->with('/app/bin/console')->andReturnTrue();
        $this->file->expects('exists')->with('/app/config/bundles.php')->andReturnFalse();

        $framework = $this->detector->detect();

        static::assertSame(Framework::Unknown, $framework);
    }
}
