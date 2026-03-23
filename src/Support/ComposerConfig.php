<?php

declare(strict_types=1);

namespace Laragear\Expose\Support;

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;

/**
 * Manages the expose configuration stored in the project's composer.json file.
 */
class ComposerConfig
{
    /**
     * Create a new Composer Config instance.
     */
    public function __construct(protected JsonConfigSource $source, protected JsonFile $file)
    {
        //
    }

    /**
     * Reads a dot-notated key from the extra.expose section.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->all();

        // If the key is empty, return the entire config array
        if (!$key) {
            return $config;
        }

        // Traverse the array using the dot-separated segments
        foreach (explode('.', $key) as $segment) {
            if (is_array($config) && array_key_exists($segment, $config)) {
                $config = $config[$segment];
            } else {
                return $default;
            }
        }

        return $config;
    }

    /**
     * Writes a dot-notated key into the extra.expose section and persists it.
     */
    public function set(string $key, mixed $value): void
    {
        $this->source->addProperty("extra.$key", $value);
    }

    /**
     * Returns the full extra.expose section as an array.
     */
    public function all(): array
    {
        return $this->file->read()['extra'] ?? [];
    }

    /**
     * Removes a given key.
     */
    public function forget(string $key): void
    {
        $this->source->removeProperty("extra.$key");
    }
}
