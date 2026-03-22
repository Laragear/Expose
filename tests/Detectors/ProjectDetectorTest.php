<?php

declare(strict_types=1);

namespace Tests\Detectors;

use Laragear\Expose\Detectors\ProjectDetector;
use Laragear\Expose\Enums\Framework;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Tests that ProjectDetector correctly identifies PHP frameworks. */
class ProjectDetectorTest extends TestCase
{
    /** Returns detection test cases as [fixture, expected Framework]. */
    public static function composerPackageProvider(): array
    {
        return [
            'Laravel via composer.json'  => ['composer-laravel.json', Framework::Laravel],
            'Lumen via composer.json'    => ['composer-lumen.json', Framework::Lumen],
            'Symfony via composer.json'  => ['composer-symfony.json', Framework::Symfony],
            'CakePHP via composer.json'  => ['composer-cakephp.json', Framework::CakePHP],
            'Yii via composer.json'      => ['composer-yii.json', Framework::Yii],
        ];
    }

    #[DataProvider('composerPackageProvider')]
    public function test_detects_framework_from_composer_json(
        string $fixture,
        Framework $expected,
    ): void {
        $this->withTempDir(function (string $dir) use ($fixture, $expected): void {
            copy($this->fixturesPath($fixture), $dir . '/composer.json');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame($expected, $framework);
        });
    }

    public function test_detects_laravel_from_env_file(): void
    {
        $this->withTempDir(function (string $dir): void {
            copy($this->fixturesPath('env-laravel'), $dir . '/.env');
            $this->writeFile($dir, 'composer.json', '{"require":{}}');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::Laravel, $framework);
        });
    }

    public function test_detects_wordpress_from_env_file(): void
    {
        $this->withTempDir(function (string $dir): void {
            copy($this->fixturesPath('env-wordpress'), $dir . '/.env');
            $this->writeFile($dir, 'composer.json', '{"require":{}}');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::WordPress, $framework);
        });
    }

    public function test_detects_laravel_from_artisan_file(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', '{"require":{}}');
            $this->writeFile($dir, 'artisan', '<?php // artisan stub');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::Laravel, $framework);
        });
    }

    public function test_detects_wordpress_from_wp_config(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', '{"require":{}}');
            $this->writeFile($dir, 'wp-config.php', '<?php // wp stub');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::WordPress, $framework);
        });
    }

    public function test_detects_symfony_from_filesystem(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', '{"require":{}}');
            $this->writeFile($dir, 'bin/console', '#!/usr/bin/env php');
            $this->writeFile($dir, 'config/bundles.php', '<?php return [];');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::Symfony, $framework);
        });
    }

    public function test_falls_back_to_unknown_when_nothing_matches(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', '{"require":{}}');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::Unknown, $framework);
        });
    }

    public function test_composer_json_takes_priority_over_env_file(): void
    {
        $this->withTempDir(function (string $dir): void {
            // composer.json says Symfony, .env says Laravel — composer wins
            copy($this->fixturesPath('composer-symfony.json'), $dir . '/composer.json');
            copy($this->fixturesPath('env-laravel'), $dir . '/.env');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::Symfony, $framework);
        });
    }

    public function test_handles_missing_composer_json_gracefully(): void
    {
        $this->withTempDir(function (string $dir): void {
            // No composer.json at all — should not throw, fall back to Unknown
            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::Unknown, $framework);
        });
    }

    public function test_handles_malformed_composer_json_gracefully(): void
    {
        $this->withTempDir(function (string $dir): void {
            $this->writeFile($dir, 'composer.json', 'NOT VALID JSON {{{');

            $framework = (new ProjectDetector($dir))->detect();

            static::assertSame(Framework::Unknown, $framework);
        });
    }
}
