<?php

declare(strict_types=1);

namespace Laragear\Expose\Support;

use RuntimeException;
use function data_forget;
use function data_set;

/**
 * Manages the expose configuration stored in the project's composer.json file.
 */
class ComposerConfig
{
    /**
     * Parsed contents of the composer.json file. @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Create a new Composer Config instance.
     */
    public function __construct(protected readonly string $path)
    {
        //
    }

    /**
     * Reads a dot-notated key from the extra.expose section.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->load()['extra']['expose'] ?? [];

        return data_get($config, $key, $default);
    }

    /**
     * Writes a dot-notated key into the extra.expose section and persists it.
     */
    public function set(string $key, mixed $value): void
    {
        $this->load();

        $this->data['extra'] ??= [];
        $this->data['extra']['expose'] ??= [];

        data_set($this->data['extra']['expose'], $key, $value);

        $this->save();
    }

    /**
     * Returns the full extra.expose section as an array.
     */
    public function all(): array
    {
        return $this->load()['extra']['expose'] ?? [];
    }

    /**
     * Removes a given key.
     */
    public function forget(string $key): void
    {
        data_forget($this->data, "extra.expose.$key");

        $this->save();
    }

    /**
     * Loads the composer.json file into memory, caching the result.
     */
    protected function load(): array
    {
        if (empty($this->data)) {
            if (!file_exists($this->path)) {
                throw new RuntimeException("composer.json not found at [$this->path].");
            }

            $this->data = json_decode((string) file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR);
        }

        return $this->data;
    }

    /**
     * Persists the in-memory data back to composer.json with readable formatting.
     */
    protected function save(): void
    {
        file_put_contents(
            $this->path,
            json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }
}
