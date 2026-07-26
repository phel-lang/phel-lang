<?php

declare(strict_types=1);

namespace Phel\Run\Application\Agent;

use FilesystemIterator;
use Phel\Run\Domain\Agent\AgentDocsManifest;
use Phel\Run\Domain\Agent\AgentDocsSyncResult;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function dirname;
use function explode;
use function in_array;
use function is_file;
use function sort;
use function sprintf;
use function str_replace;

/**
 * Incremental `.agents/` sync.
 *
 * The old copy was all-or-nothing: the whole tree was skipped when it existed,
 * and `--force` then rewrote every file. This walks the bundled tree file by
 * file and classifies each one against the install manifest, so a re-run picks
 * up new and updated docs while leaving anything the user edited exactly where
 * it is.
 *
 * @internal
 */
final readonly class AgentDocsSynchronizer
{
    private const string EXAMPLES_SUBDIR = 'examples';

    /** Change detection only, never a security boundary. */
    private const string HASH_ALGO = 'xxh128';

    public function __construct(
        private AgentManifestStore $manifestStore = new AgentManifestStore(),
    ) {}

    public function sync(
        string $sourceRoot,
        string $docsDir,
        string $version,
        bool $force,
        bool $withExamples,
        bool $dryRun,
    ): AgentDocsSyncResult {
        $previous = $this->manifestStore->load($docsDir) ?? AgentDocsManifest::empty();

        $created = [];
        $updated = [];
        $unchanged = [];
        $skipped = [];
        $backedUp = [];
        $shipped = [];

        foreach ($this->sourceFiles($sourceRoot, $withExamples) as $relative => $sourcePath) {
            $sourceHash = $this->hash($sourcePath);
            $shipped[$relative] = $sourceHash;
            $target = $docsDir . '/' . $relative;

            if (!is_file($target)) {
                $created[] = $relative;
                $this->install($sourcePath, $target, $dryRun);
                continue;
            }

            $targetHash = $this->hash($target);
            if ($targetHash === $sourceHash) {
                $unchanged[] = $relative;
                continue;
            }

            if ($previous->shippedHash($relative) === $targetHash) {
                // Byte-identical to what we last wrote, so nothing of the user's
                // is at stake: refresh it.
                $updated[] = $relative;
                $this->install($sourcePath, $target, $dryRun);
                continue;
            }

            if (!$force) {
                $skipped[] = $relative;
                continue;
            }

            $backedUp[] = $relative;
            if (!$dryRun) {
                AgentFileOperations::copy($target, $target . AgentInstaller::BACKUP_SUFFIX);
            }

            $this->install($sourcePath, $target, $dryRun);
        }

        if (!$dryRun && $shipped !== []) {
            $this->manifestStore->save($docsDir, $previous->with($version, $shipped));
        }

        sort($created);
        sort($updated);
        sort($unchanged);
        sort($skipped);
        sort($backedUp);

        return new AgentDocsSyncResult($created, $updated, $unchanged, $skipped, $backedUp);
    }

    /**
     * Remove only the paths the manifest claims, then any directory that ends up
     * empty. Without a manifest we cannot tell our files from the user's, so the
     * whole tree goes, which is what every release before the manifest did.
     */
    public function remove(string $docsDir): void
    {
        $manifest = $this->manifestStore->load($docsDir);
        if (!$manifest instanceof AgentDocsManifest) {
            $this->removeTree($docsDir);
            return;
        }

        foreach ($manifest->paths() as $relative) {
            $path = $docsDir . '/' . $relative;
            if (is_file($path)) {
                AgentFileOperations::delete($path);
            }
        }

        $this->manifestStore->delete($docsDir);
        $this->pruneEmptyDirectories($docsDir);
    }

    /**
     * The docs version recorded in $docsDir, or null when nothing there was
     * installed by a release that writes a manifest.
     */
    public function installedVersion(string $docsDir): ?string
    {
        $manifest = $this->manifestStore->load($docsDir);
        if (!$manifest instanceof AgentDocsManifest || $manifest->version === '') {
            return null;
        }

        return $manifest->version;
    }

    /**
     * Relative path => absolute source path, for every file the sync covers.
     *
     * @return iterable<string, string>
     */
    private function sourceFiles(string $sourceRoot, bool $withExamples): iterable
    {
        $skipTopLevel = $withExamples ? [] : [self::EXAMPLES_SUBDIR];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isDir()) {
                continue;
            }

            $relative = str_replace('\\', '/', $iterator->getSubPathname());
            if (in_array(explode('/', $relative, 2)[0], $skipTopLevel, true)) {
                continue;
            }

            yield $relative => $item->getPathname();
        }
    }

    private function install(string $source, string $target, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        AgentFileOperations::ensureDirectory(dirname($target));
        AgentFileOperations::copy($source, $target);
    }

    private function hash(string $path): string
    {
        $hash = hash_file(self::HASH_ALGO, $path);
        if ($hash === false) {
            throw new RuntimeException(sprintf('Cannot read file: %s', $path));
        }

        return $hash;
    }

    private function pruneEmptyDirectories(string $dir): void
    {
        foreach ($this->deepestFirst($dir) as $item) {
            if ($item instanceof SplFileInfo && $item->isDir() && $this->isEmpty($item->getPathname())) {
                AgentFileOperations::deleteDirectory($item->getPathname());
            }
        }

        if ($this->isEmpty($dir)) {
            AgentFileOperations::deleteDirectory($dir);
        }
    }

    private function removeTree(string $dir): void
    {
        foreach ($this->deepestFirst($dir) as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isDir()) {
                AgentFileOperations::deleteDirectory($item->getPathname());
            } else {
                AgentFileOperations::delete($item->getPathname());
            }
        }

        AgentFileOperations::deleteDirectory($dir);
    }

    /**
     * Children before their parent, so a directory is visited once everything
     * inside it has already been dealt with.
     *
     * @return RecursiveIteratorIterator<RecursiveDirectoryIterator>
     */
    private function deepestFirst(string $dir): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
    }

    private function isEmpty(string $dir): bool
    {
        return !new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS)->valid();
    }
}
