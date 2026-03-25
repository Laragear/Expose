<?php

namespace Laragear\Expose\Support;

use function file_get_contents;
use function ltrim;
use function stream_context_create;

/**
 * @codeCoverageIgnore
 */
class Http
{
    /**
     * Retrieve the contents of a localhost endpoint.
     */
    public function localGet(int $port, string $path, bool $ignoreErrors = true): string|false
    {
        $path = ltrim($path, '/');

        return @file_get_contents("http://localhost:$port/$path", false, stream_context_create([
            'http' => [
                'timeout'       => 2,
                'ignore_errors' => $ignoreErrors,
            ],
        ]));
    }
}
