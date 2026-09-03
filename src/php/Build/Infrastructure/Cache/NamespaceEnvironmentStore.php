<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Phel\Shared\Facade\CompilerFacadeInterface;

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
 *
 * @phpstan-import-type SerializedNamespaceEnvironment from CompilerFacadeInterface
 *
 * @internal
 */
final class NamespaceEnvironmentStore
{
    /**
     * In-memory memo of per-namespace environment data, keyed by env-file
     * path. Mutable across calls, so the class is not `readonly`.
     *
     * @var array<string, SerializedNamespaceEnvironment|null>
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
     * @param SerializedNamespaceEnvironment $envData
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
     * @return SerializedNamespaceEnvironment|null
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

        // The env file is written by put() above, never by a user.
        /** @var SerializedNamespaceEnvironment|null $data */
        $data = require $envPath;

        return $this->memo[$envPath] = $data;
    }

    public function clearMemo(): void
    {
        $this->memo = [];
    }
}
