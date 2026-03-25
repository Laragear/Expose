<?php

use Laragear\Expose\Container\Container;

if (!function_exists('app')) {
    /**
     * Resolves a given service, or returns the container instance.
     *
     * @template TService
     *
     * @param  class-string<TService>|null  $class
     * @return ($class is null ? \Laragear\Expose\Container\Container : TService)
     */
    function app(?string $class = null, array $parameters = []): mixed
    {
        $app = Container::getInstance();

        return $class ? $app->make($class, $parameters) : $app;
    }
}
