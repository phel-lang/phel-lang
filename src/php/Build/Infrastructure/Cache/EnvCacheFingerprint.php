<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use function array_unique;
use function getenv;
use function implode;
use function md5;
use function sort;

/**
 * Hash of the environment variables a project declares as compile inputs
 * (`cache-env-vars`), mixed into the compiled-code cache key.
 *
 * A macro that reads `php/getenv` bakes the value into the PHP it emits, and
 * the cache is otherwise keyed by source alone, so a changed variable used to
 * serve the previous expansion (#3236). Declaring the variable makes the flip
 * a cache miss.
 *
 * @internal
 */
final class EnvCacheFingerprint
{
    /**
     * Read once per process, at wiring time: a macro expanding later in the
     * same process sees whatever `putenv` left behind, the key does not.
     *
     * @param list<string> $names Environment variable names, in any order
     */
    public static function of(array $names): string
    {
        if ($names === []) {
            return '';
        }

        $names = array_unique($names);
        // Sorted so reordering the config does not invalidate every entry.
        sort($names);

        $parts = [];
        foreach ($names as $name) {
            $value = getenv($name);
            // Hashed, not inlined: a value spelling out `|OTHER=x` would
            // otherwise forge another pair and collide with a different state.
            $parts[] = $name . '=' . ($value === false ? '-' : md5($value));
        }

        return md5(implode('|', $parts));
    }
}
