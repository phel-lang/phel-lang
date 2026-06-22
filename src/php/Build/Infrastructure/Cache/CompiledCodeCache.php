<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use ParseError;
use Phel\Build\Domain\Cache\CompiledCodeCacheInterface;

use function count;
use function function_exists;
use function token_get_all;

use const TOKEN_PARSE;

/**
 * Caches compiled PHP code keyed by source file path with content-hash
 * validation. Multiple files that share a namespace via `(in-ns ...)`
 * each get their own cache entry, so they do not clobber one another.
 *
 * This class owns the cache policy: the live entry index, LRU eviction, and
 * the put-time PHP-validity gate. It delegates the on-disk concerns to
 * collaborators: {@see CacheIndexFile} serialises the entry index,
 * {@see NamespaceEnvironmentStore} stores per-namespace env data,
 * {@see CachePathResolver} computes paths, {@see AtomicFileWriter} writes
 * files, and {@see CacheDirectory} owns the directory layout.
 *
 * @phpstan-type CacheEntry array{namespace: string, source_hash: string, compiled_path: string, last_accessed: int}
 */
final class CompiledCodeCache implements CompiledCodeCacheInterface
{
    use DeferredFlushTrait;

    /** @var array<string, CacheEntry> */
    private array $entries = [];

    /**
     * Source paths invalidated in this process that have not been re-`put`.
     * Used as tombstones so the disk-merge in `CacheIndexFile::save()` cannot
     * resurrect entries that downstream cascades meant to remove.
     *
     * @var array<string, true>
     */
    private array $tombstones = [];

    /**
     * Source paths put or read in this process. A build `(load ...)`s its
     * secondaries into the cache and the `SecondaryFileHarvester` reads them
     * back at the end of the same run; LRU eviction triggered by a later
     * `put` must never delete a file this run still needs, or the build
     * would ship a `(load ...)` with no compiled sibling.
     *
     * @var array<string, true>
     */
    private array $touchedThisProcess = [];

    private bool $loaded = false;

    private readonly CacheDirectory $directory;

    private readonly CachePathResolver $pathResolver;

    private readonly AtomicFileWriter $fileWriter;

    private readonly CacheIndexFile $indexFile;

    private readonly NamespaceEnvironmentStore $environmentStore;

    public function __construct(
        string $cacheDir,
        string $phelVersion = '',
        private readonly int $maxEntries = 500,
        ?CachePathResolver $pathResolver = null,
        ?AtomicFileWriter $fileWriter = null,
        ?CacheDirectory $directory = null,
        ?CacheIndexFile $indexFile = null,
        ?NamespaceEnvironmentStore $environmentStore = null,
    ) {
        $this->directory = $directory ?? new CacheDirectory($cacheDir);
        $this->pathResolver = $pathResolver ?? new CachePathResolver($cacheDir);
        $this->fileWriter = $fileWriter ?? new AtomicFileWriter();
        $this->indexFile = $indexFile ?? new CacheIndexFile($this->directory, $phelVersion);
        $this->environmentStore = $environmentStore
            ?? new NamespaceEnvironmentStore($this->directory, $this->pathResolver, $this->fileWriter);
    }

    /**
     * Returns the path to the cached compiled PHP file if it exists and
     * matches the source content, or null otherwise.
     *
     * @param string $sourcePath Absolute path to the .phel source file
     * @param string $sourceHash MD5 hash of the source file content
     */
    public function get(string $sourcePath, string $sourceHash): ?string
    {
        $this->loadEntries();

        $entry = $this->entries[$sourcePath] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($entry['source_hash'] !== $sourceHash) {
            return null;
        }

        $compiledPath = $this->getCompiledPath($sourcePath, $entry['namespace']);

        if (!file_exists($compiledPath)) {
            return null;
        }

        $this->entries[$sourcePath]['last_accessed'] = time();
        $this->touchedThisProcess[$sourcePath] = true;

        return $compiledPath;
    }

    /**
     * Returns true if an entry exists for this source file, regardless
     * of whether the hash matches. Used to distinguish "first build"
     * (no entry at all) from "source changed" (stale entry).
     */
    public function has(string $sourcePath): bool
    {
        $this->loadEntries();

        return isset($this->entries[$sourcePath]);
    }

    /**
     * Caches compiled PHP code for a source file.
     */
    public function put(string $sourcePath, string $namespace, string $sourceHash, string $phpCode): void
    {
        $this->loadEntries();
        $this->directory->ensure();

        $compiledPath = $this->getCompiledPath($sourcePath, $namespace);
        $fullPhpCode = "<?php\n" . $phpCode;

        if (!$this->isValidPhp($fullPhpCode)) {
            return;
        }

        if (!$this->fileWriter->write($compiledPath, $fullPhpCode)) {
            return;
        }

        $this->entries[$sourcePath] = [
            'namespace' => $namespace,
            'source_hash' => $sourceHash,
            'compiled_path' => $compiledPath,
            'last_accessed' => time(),
        ];
        $this->touchedThisProcess[$sourcePath] = true;
        unset($this->tombstones[$sourcePath]);

        $this->evictLRU();
        $this->markFlushPending();

        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($compiledPath);
        }
    }

    /**
     * Returns the path where compiled PHP for a source file would live.
     */
    public function getCompiledPath(string $sourcePath, string $namespace): string
    {
        return $this->pathResolver->compiledPath($namespace, $sourcePath, '.php');
    }

    /**
     * Returns the path where namespace-level environment data would live.
     * Environment data (refers/aliases) is shared across all files of the
     * namespace, so it is keyed by namespace alone.
     */
    public function getEnvironmentPath(string $namespace): string
    {
        return $this->environmentStore->path($namespace);
    }

    /**
     * @param array<string, mixed> $envData
     */
    public function putEnvironment(string $namespace, array $envData): void
    {
        $this->environmentStore->put($namespace, $envData);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEnvironment(string $namespace): ?array
    {
        return $this->environmentStore->get($namespace);
    }

    /**
     * Invalidates the cached compiled code for a single source file.
     * Environment data for the namespace is NOT removed because other
     * files of the same namespace may still rely on it.
     */
    public function invalidate(string $sourcePath): void
    {
        $this->loadEntries();

        $entry = $this->entries[$sourcePath] ?? null;
        if ($entry === null) {
            return;
        }

        $compiledPath = $this->getCompiledPath($sourcePath, $entry['namespace']);
        if (file_exists($compiledPath)) {
            @unlink($compiledPath);
        }

        unset($this->entries[$sourcePath], $this->touchedThisProcess[$sourcePath]);
        $this->tombstones[$sourcePath] = true;
        $this->markFlushPending();
    }

    /**
     * Clears every cached compiled file and every namespace env file.
     */
    public function clear(): void
    {
        $compiledDir = $this->directory->compiledDir();
        if (is_dir($compiledDir)) {
            $files = glob($compiledDir . '/*.php');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }

        $this->entries = [];
        $this->tombstones = [];
        $this->touchedThisProcess = [];
        $this->saveEntries();
        $this->clearFlushPending();
        $this->environmentStore->clearMemo();
    }

    /**
     * Flushes the in-memory index to disk if it has unsaved mutations.
     *
     * Registered as a `register_shutdown_function` handler by `put`/
     * `invalidate` so the index is written exactly once per process instead
     * of once per `put`. The underlying {@see CacheIndexFile::save()} keeps
     * its atomic write + `flock` + read-merge-from-disk step, so concurrent
     * `phel test` workers still merge their entries without clobbering one
     * another even though each only writes at shutdown.
     */
    public function save(): void
    {
        if (!$this->isFlushPending()) {
            return;
        }

        $this->saveEntries();
        $this->clearFlushPending();
    }

    private function loadEntries(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->entries = $this->indexFile->load();
        $this->loaded = true;
    }

    private function saveEntries(): void
    {
        $this->entries = $this->indexFile->save($this->entries, $this->tombstones);
    }

    private function isValidPhp(string $phpCode): bool
    {
        try {
            // @phpstan-ignore function.resultUnused (we only care if it throws)
            token_get_all($phpCode, TOKEN_PARSE);
            return true;
        } catch (ParseError) {
            return false;
        }
    }

    /**
     * Evicts least-recently-used entries when the entry count exceeds
     * `$maxEntries`. Removes ~10% of the oldest entries.
     *
     * Entries touched in this process are never evicted: a build loads its
     * secondaries into the cache and the `SecondaryFileHarvester` reads them
     * back at the end of the same run, so dropping one mid-build would ship
     * a `(load ...)` with no compiled sibling. This makes `maxEntries` a soft
     * cap for the current run; stale entries from earlier runs reclaim space
     * on the next process.
     */
    private function evictLRU(): void
    {
        if (count($this->entries) <= $this->maxEntries) {
            return;
        }

        uasort($this->entries, static fn($a, $b): int => $a['last_accessed'] <=> $b['last_accessed']);

        $evictCount = max(1, (int) floor($this->maxEntries / 10));

        $evicted = 0;
        foreach (array_keys($this->entries) as $sourcePath) {
            if ($evicted >= $evictCount) {
                break;
            }

            if (isset($this->touchedThisProcess[$sourcePath])) {
                continue;
            }

            $entry = $this->entries[$sourcePath];
            $compiledPath = $this->getCompiledPath($sourcePath, $entry['namespace']);
            if (file_exists($compiledPath)) {
                @unlink($compiledPath);
            }

            unset($this->entries[$sourcePath]);
            ++$evicted;
        }
    }
}
