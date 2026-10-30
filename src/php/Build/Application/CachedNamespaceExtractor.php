<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Iterator;
use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
use Phel\Build\Domain\Cache\NullScanIndexCache;
use Phel\Build\Domain\Cache\ScanIndexCacheInterface;
use Phel\Build\Domain\Cache\ScanIndexEntry;
use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceFileGrouper;
use Phel\Build\Domain\Extractor\NamespaceSorterInterface;
use Phel\Build\Domain\Extractor\SourcePathResolver;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\MissingNsFormException;
use Phel\Shared\NamespaceInformation;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;
use UnexpectedValueException;

use function is_array;

/**
 * @phpstan-import-type DirFingerprint from ScanIndexEntry
 *
 * @internal
 */
final class CachedNamespaceExtractor implements NamespaceExtractorInterface
{
    private readonly NamespaceFileGrouper $grouper;

    private readonly ExcludedScanPaths $excludedPaths;

    /** @var array<string, list<NamespaceInformation>> */
    private array $directoriesScanCache = [];

    private readonly ScanIndexCacheInterface $scanIndexCache;

    public function __construct(
        private readonly NamespaceExtractorInterface $innerExtractor,
        private readonly NamespaceCacheInterface $cache,
        NamespaceSorterInterface $namespaceSorter,
        ?ExcludedScanPaths $excludedPaths = null,
        ?ScanIndexCacheInterface $scanIndexCache = null,
    ) {
        $this->grouper = new NamespaceFileGrouper($namespaceSorter);
        $this->excludedPaths = $excludedPaths ?? ExcludedScanPaths::none();
        $this->scanIndexCache = $scanIndexCache ?? new NullScanIndexCache();
    }

    public function getNamespaceFromFile(string $path): NamespaceInformation
    {
        $realPath = SourcePathResolver::resolve($path);
        if ($realPath === null) {
            return $this->innerExtractor->getNamespaceFromFile($path);
        }

        $cachedEntry = $this->cache->get($realPath);
        if ($cachedEntry instanceof NamespaceCacheEntry && $cachedEntry->isValid()) {
            return $cachedEntry->toNamespaceInformation();
        }

        // Taken before the read: a rewrite landing between the read and the
        // `filemtime` below must still count as racy.
        $recordedAt = time();
        $info = $this->innerExtractor->getNamespaceFromFile($path);
        $this->cacheNamespaceInfo($info, $recordedAt);

        return $info;
    }

    /**
     * @param list<string> $directories
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories, bool $failOnInvalidNsForm = false): array
    {
        // A strict scan (`phel build`) reads every file instead of trusting
        // either cache's mtime check, so a build always sees the `ns` form it
        // has to fail on.
        if ($failOnInvalidNsForm) {
            return $this->innerExtractor->getNamespacesFromDirectories($directories, true);
        }

        $cacheKey = $this->scanCacheKey($directories);

        // First-level, intra-process cache: an exact dir-set repeat within the
        // same run skips even the persisted-index validation.
        if (isset($this->directoriesScanCache[$cacheKey])) {
            return $this->directoriesScanCache[$cacheKey];
        }

        // Validate the indexed directory and file stamps without reopening
        // directories. Recent stamps remain racy and force a fresh walk.
        $persisted = $this->scanIndexCache->get($cacheKey);
        if ($persisted instanceof ScanIndexEntry
            && $persisted->covers($this->existingDirectories($directories))
            && $persisted->isValid()
        ) {
            return $this->directoriesScanCache[$cacheKey] = $persisted->infos;
        }

        // Taken before the walk: see `ScanIndexEntry::isValid()`.
        $recordedAt = time();
        $allInfos = [];
        $skippedFiles = [];
        $perDir = [];
        foreach ($this->findAllPhelFiles($directories, $perDir) as $file) {
            try {
                $allInfos[] = $this->getNamespaceFromFile($file);
            } catch (AnalyzerException|ExtractorException|MissingNsFormException|CompilerException) {
                // Skip a file that cannot be lexed, whose first form is not ns
                // and does not analyse, or whose ns form does not analyse, so
                // one stray file in a scanned directory does not abort the
                // whole scan (e.g. `phel eval` in a cwd holding unrelated
                // Clojure checkouts, #3484). The index still stamps it, so
                // fixing it in place forces a new walk.
                $skippedFiles[] = $file;
            }
        }

        $grouped = $this->grouper->groupAndSort($allInfos);

        $this->scanIndexCache->put($cacheKey, $perDir, $grouped, $recordedAt, $skippedFiles);

        return $this->directoriesScanCache[$cacheKey] = $grouped;
    }

    /**
     * The resolved paths of the requested directories that exist right now.
     * A configured directory that does not exist yet (a project whose
     * `tests/` is created after the first `phel` command ran) is left out
     * of the fingerprint, so its later appearance is detected by comparing
     * this list against the fingerprint's keys rather than by an mtime.
     *
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function existingDirectories(array $directories): array
    {
        $existing = [];
        foreach ($directories as $directory) {
            $realpath = SourcePathResolver::resolve($directory);
            if ($realpath !== null && is_dir($realpath)) {
                $existing[] = $realpath;
            }
        }

        return $existing;
    }

    /**
     * @param list<string> $directories
     */
    private function scanCacheKey(array $directories): string
    {
        $resolved = [];
        foreach ($directories as $directory) {
            $real = SourcePathResolver::resolve($directory);
            $resolved[] = $real ?? $directory;
        }

        sort($resolved);

        return implode("\0", $resolved);
    }

    private function cacheNamespaceInfo(NamespaceInformation $info, int $recordedAt): void
    {
        $file = $info->getFile();
        $mtime = @filemtime($file);

        if ($mtime === false) {
            return;
        }

        $entry = new NamespaceCacheEntry(
            $file,
            $mtime,
            $info->getNamespace(),
            $info->getDependencies(),
            $info->isPrimaryDefinition(),
            $recordedAt,
        );

        $this->cache->put($file, $entry);
    }

    /**
     * @param list<string>                  $directories
     * @param array<string, DirFingerprint> $perDir
     *
     * @return list<string>
     */
    private function findAllPhelFiles(array $directories, array &$perDir): array
    {
        $files = [];

        foreach ($this->existingDirectories($directories) as $realpath) {
            $perDir[$realpath] = ['mtime' => @filemtime($realpath) ?: 0];
            try {
                foreach ($this->phelFileIterator($realpath, $perDir) as $file) {
                    if (!is_array($file)) {
                        continue;
                    }

                    /** @var array<int, string> $file */
                    if ($this->excludedPaths->contains($file[0], $realpath)) {
                        continue;
                    }

                    $resolvedFile = SourcePathResolver::resolve($file[0]);
                    if ($resolvedFile !== null) {
                        $files[] = $resolvedFile;
                    }
                }
            } catch (UnexpectedValueException) {
                $perDir[$realpath] = ['mtime' => 0];
                continue;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Build the recursive iterator that yields `.phel`/`.cljc` files under
     * `$root`, pruning subtrees flagged by `ExcludedScanPaths` at descent
     * time so vendor/.git/node_modules never get walked.
     *
     * `RegexIterator::GET_MATCH` yields each path as a `preg_match` result
     * array, so the offset-0 capture holds the matched filename (narrowed at
     * the call site since `RegexIterator`'s generic cannot express it).
     *
     * @param array<string, DirFingerprint> $perDir
     *
     * @return Iterator<mixed, mixed>
     */
    private function phelFileIterator(string $root, array &$perDir): Iterator
    {
        $directoryIterator = new RecursiveDirectoryIterator(
            $root,
            RecursiveDirectoryIterator::SKIP_DOTS,
        );
        $excludedPaths = $this->excludedPaths;
        $prunedDescent = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            static function (mixed $current) use ($excludedPaths, $root, &$perDir): bool {
                if (!$current instanceof SplFileInfo || !$current->isDir()) {
                    return true;
                }

                if ($excludedPaths->shouldPruneDirectory(
                    $current->getFilename(),
                    $current->getPathname(),
                    $root,
                )) {
                    return false;
                }

                $perDir[$current->getPathname()] = ['mtime' => @filemtime($current->getPathname()) ?: 0];

                return true;
            },
        );

        $iterator = new RecursiveIteratorIterator($prunedDescent);

        return new RegexIterator(
            $iterator,
            '/^.+\.(phel|cljc)$/i',
            RegexIterator::GET_MATCH,
        );
    }
}
