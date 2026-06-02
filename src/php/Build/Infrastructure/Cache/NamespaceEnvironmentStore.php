<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use function array_key_exists;
use function var_export;

/**
 * Stores per-namespace environment data (refers/aliases) as a `require`-able
 * PHP file under the cache's `compiled/` directory.
 *
 * Environment data is shared across every file of a namespace, so it is
 * keyed by namespace alone. Reads are memoised by env-file path: a
 * `(load ...)` chain for one namespace would otherwise re-`require` (and,
 * without opcache, re-parse) the same env file once per secondary.
 */
final class NamespaceEnvironmentStore
{
    /**
     * In-memory memo of per-namespace environment data, keyed by env-file
     * path. Mutable across calls, so the class is not `readonly`.
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $memo = [];

    public function __construct(
        private readonly CacheDirectory $directory,
        private readonly CachePathResolver $pathResolver,
        private readonly AtomicFileWriter $fileWriter,
    ) {}

    public function path(string $namespace): string
    {
        return $this->pathResolver->environmentPath($namespace);
    }

    /**
     * @param array<string, mixed> $envData
     */
    public function put(string $namespace, array $envData): void
    {
        $this->directory->ensure();

        $envPath = $this->path($namespace);
        $content = '<?php return ' . var_export($envData, true) . ';';

        $this->fileWriter->write($envPath, $content);
        $this->memo[$envPath] = $envData;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $namespace): ?array
    {
        $envPath = $this->path($namespace);

        if (array_key_exists($envPath, $this->memo)) {
            return $this->memo[$envPath];
        }

        if (!file_exists($envPath)) {
            return $this->memo[$envPath] = null;
        }

        /** @var array<string, mixed>|null $data */
        $data = require $envPath;

        return $this->memo[$envPath] = $data;
    }

    public function clearMemo(): void
    {
        $this->memo = [];
    }
}
