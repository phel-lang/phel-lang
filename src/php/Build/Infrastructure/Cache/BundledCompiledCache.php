<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use function is_array;
use function is_file;
use function str_replace;

/**
 * Read-only, content-addressed cache of precompiled bundled code shipped with
 * the Phel distribution (primarily the PHAR). It lets a cold `phel run` in any
 * project reuse the precompiled `phel.*` stdlib instead of recompiling it.
 *
 * Unlike {@see CompiledCodeCache}, lookups are keyed by the source content
 * hash, not the source file path. The PHAR is mounted at a different absolute
 * path on every machine, so a path-keyed entry would never match; a content
 * hash is install-location independent and matches as long as the bundled
 * `.phel` source is byte-identical to the one being run.
 *
 * Layout under {@see $dir}:
 *   <sourceHash>.php       compiled PHP for a source file
 *   <munged-ns>.env.php    per-namespace environment data (refers/aliases)
 */
final readonly class BundledCompiledCache
{
    public function __construct(
        private string $dir,
    ) {}

    /**
     * Path to the precompiled PHP for the given source hash, or null when the
     * bundle has no matching entry.
     */
    public function compiledPath(string $sourceHash): ?string
    {
        $path = $this->dir . '/' . $sourceHash . '.php';

        return is_file($path) ? $path : null;
    }

    /**
     * Per-namespace environment data, or null when the bundle has none.
     *
     * @return array<string, mixed>|null
     */
    public function environment(string $namespace): ?array
    {
        $path = $this->dir . '/' . $this->mungeNamespace($namespace) . '.env.php';

        if (!is_file($path)) {
            return null;
        }

        /** @var mixed $data */
        $data = require $path;

        if (!is_array($data)) {
            return null;
        }

        $environment = [];
        foreach ($data as $key => $value) {
            $environment[(string) $key] = $value;
        }

        return $environment;
    }

    /**
     * Absolute path where the compiled PHP for a source hash would live.
     * Used by the precompiler when writing the bundle.
     */
    public function compiledTarget(string $sourceHash): string
    {
        return $this->dir . '/' . $sourceHash . '.php';
    }

    /**
     * Absolute path where the env data for a namespace would live.
     * Used by the precompiler when writing the bundle.
     */
    public function environmentTarget(string $namespace): string
    {
        return $this->dir . '/' . $this->mungeNamespace($namespace) . '.env.php';
    }

    private function mungeNamespace(string $namespace): string
    {
        return str_replace(['\\', '/'], '_', $namespace);
    }
}
