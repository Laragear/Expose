<?php

declare(strict_types=1);

namespace Laragear\Expose\Detectors;

use Laragear\Expose\Enums\Framework;
use Laragear\Expose\Support\File;
use const DIRECTORY_SEPARATOR as DS;

/**
 * Detects which PHP framework or platform the current project uses.
 */
class ProjectDetector
{
    /**
     * Maps composer package names to their corresponding Framework.
     */
    protected const array COMPOSER_PACKAGE_MAP = [
        'laravel/framework' => Framework::Laravel,
        'laravel/lumen-framework' => Framework::Lumen,
        'symfony/framework-bundle' => Framework::Symfony,
        'cakephp/cakephp' => Framework::CakePHP,
        'yiisoft/yii2' => Framework::Yii,
    ];

    /**
     * Maps .env variable names that signal a specific framework.
     */
    protected const array ENV_VAR_SIGNATURES = [
        'LARAVEL_BYPASS_ENV_CHECK' => Framework::Laravel,
        'APP_KEY' => Framework::Laravel,
        'SYMFONY_ENVIRONMENT' => Framework::Symfony,
        'CAKE_ENV' => Framework::CakePHP,
        'YII_ENV' => Framework::Yii,
        'WP_HOME' => Framework::WordPress,
        'DB_NAME' => Framework::WordPress,
    ];

    /**
     * Create a new Project Detector instance.
     */
    public function __construct(protected File $file, protected readonly string $projectRoot)
    {
        //
    }

    /**
     * Detects and returns the framework used in the project root.
     */
    public function detect(): Framework
    {
        return $this->detectFromComposerJson()
            ?? $this->detectFromEnvFile()
            ?? $this->detectFromEnvVars()
            ?? $this->detectFromFilesystem()
            ?? Framework::Unknown;
    }

    /**
     * Tries to detect the framework from the project's composer.json.
     */
    protected function detectFromComposerJson(): ?Framework
    {
        $composerJson = $this->readComposerJson();

        if ($composerJson === null) {
            return null;
        }

        $packages = array_keys(array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? [],
        ));

        foreach (self::COMPOSER_PACKAGE_MAP as $package => $framework) {
            if (in_array($package, $packages, true)) {
                return $framework;
            }
        }

        return null;
    }

    /**
     * Tries to detect the framework from the project's .env file.
     */
    protected function detectFromEnvFile(): ?Framework
    {
        $envPath = $this->projectRoot.DS.'.env';

        if ($this->file->missing($envPath)) {
            return null;
        }

        return $this->matchEnvVars($this->parseEnvFile($envPath));
    }

    /**
     * Tries to detect the framework from environment variables already set in the shell.
     */
    protected function detectFromEnvVars(): ?Framework
    {
        return $this->matchEnvVars($_ENV + getenv());
    }

    /**
     * Tries to detect the framework by checking for well-known files in the project root.
     */
    protected function detectFromFilesystem(): ?Framework
    {
        if ($this->file->exists($this->projectRoot.DS.'wp-config.php')
            || $this->file->exists($this->projectRoot.DS.'wp-blog-header.php')) {
            return Framework::WordPress;
        }

        if ($this->file->exists($this->projectRoot.DS.'artisan')) {
            return Framework::Laravel;
        }

        if ($this->file->exists($this->projectRoot.DS.'bin'.DS.'console')
            && $this->file->exists($this->projectRoot.DS.'config'.DS.'bundles.php')) {
            return Framework::Symfony;
        }

        return null;
    }

    /**
     * Parses a .env file into a key-presence map.
     *
     * @return array<string, true>
     */
    protected function parseEnvFile(string $path): array
    {
        $vars = [];

        foreach ($this->file->lines($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key] = explode('=', $line, 2) + ['', ''];

            $vars[trim($key)] = true;
        }

        return $vars;
    }

    /**
     * Looks up the env-var signature map against the given variable bag.
     */
    protected function matchEnvVars(array $vars): ?Framework
    {
        foreach (self::ENV_VAR_SIGNATURES as $envKey => $framework) {
            if (array_key_exists($envKey, $vars)) {
                return $framework;
            }
        }

        return null;
    }

    /**
     * Reads and decodes the project's composer.json, returning null on failure.
     *
     * @return array<string, mixed>|null
     */
    protected function readComposerJson(): ?array
    {
        $path = $this->projectRoot.DS.'composer.json';

        if ($this->file->missing($path)) {
            return null;
        }

        $decoded = json_decode($this->file->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
