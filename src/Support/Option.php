<?php

namespace Laragear\Expose\Support;

/** @phpstan-consistent-constructor */
class Option
{
    /**
     * Create a new Option instance.
     */
    public function __construct(
        readonly public string $label,
        readonly public ?string $default,
        readonly public bool $isSecret,
        protected bool $required = true,
    ) {
        //
    }

    /**
     * Sets this option as not-required.
     */
    public function optional(): static
    {
        $this->required = false;

        return $this;
    }

    /**
     * Check if this option is required.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Check if this option is not required.
     */
    public function isNotRequired(): bool
    {
        return ! $this->required;
    }

    /**
     * Create an Option instance.
     */
    public static function name(string $label, ?string $default = null): static
    {
        return new static($label, $default, false);
    }

    /**
     * Create an Option instance that's secret.
     */
    public static function secret(string $label, ?string $default = null): static
    {
        return new static($label, $default, true);
    }
}
