<?php

namespace Laragear\Expose\Support;

use function usleep;

class Date
{
    /**
     * Returns the current UNIX timestamp.
     */
    public function now(int $calc = 0): int
    {
        return time() + $calc;
    }

    /**
     * Sleeps.
     */
    public function sleep(int $seconds = 1): void
    {
        usleep($seconds * 1000);
    }
}
