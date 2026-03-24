<?php

namespace Laragear\Expose\Container;

use Godruoyi\Container\Container as BaseContainer;

class Container extends BaseContainer
{
    /**
     * Returns the container instance.
     */
    public static function getInstance(): static
    {
        return static::$instance ??= new static();
    }

    /**
     * Sets the container instance.
     */
    public static function setInstance(?self $container = null): void
    {
        static::$instance = $container ?? new static();
    }
}
