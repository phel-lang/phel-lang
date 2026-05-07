<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use function in_array;
use function strlen;

/**
 * Prefixes that should be skipped during a recursive namespace scan. Combines
 * pre-resolved absolute directories with a basename that prunes
 * `<scan_root>/<basename>/` per walk, so a build output never shadows real
 * sources regardless of which scan root the caller passes in.
 */
final readonly class ExcludedScanPaths
{
    /**
     * Segments always pruned from a namespace scan regardless of scan root.
     *
     * `vendor/`, `.git/`, `node_modules/` never carry user Phel sources and
     * dominate descent cost when the scan root is the project root.
     *
     * Agent tooling (Claude Code, Codex, etc.) drops repo clones under a
     * `worktrees/` directory whose `src/phel/` would shadow real sources.
     * Agent config directories can contain examples, scripts, or worktrees
     * whose Phel files must not leak into the host repo's scan.
     */
    private const array ALWAYS_EXCLUDED_SEGMENTS = [
        DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'worktrees' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR,
    ];

    /**
     * Basenames of directories always pruned at descent time. Matched against
     * `RecursiveDirectoryIterator` directory entries before recursion to avoid
     * walking subtrees that cannot contain user Phel sources.
     */
    private const array ALWAYS_PRUNED_BASENAMES = [
        'vendor',
        '.git',
        'node_modules',
        'worktrees',
        '.agents',
    ];

    /** @var list<string> */
    private array $absolutePrefixes;

    /**
     * @param list<string> $excludedDirectories absolute paths whose subtree is skipped
     * @param string       $destDirBasename     when non-empty, any `<scan_root>/<basename>/`
     *                                          subtree is skipped per walk
     */
    public function __construct(
        array $excludedDirectories = [],
        private string $destDirBasename = '',
    ) {
        $this->absolutePrefixes = $this->normalizePrefixes($excludedDirectories);
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * Returns `true` when a directory entry must be skipped at descent time
     * during a recursive scan. Pruning here is much cheaper than discarding
     * files emitted by the underlying iterator.
     */
    public function shouldPruneDirectory(string $basename, string $absolutePath, string $scanRoot): bool
    {
        if (in_array($basename, self::ALWAYS_PRUNED_BASENAMES, true)) {
            return true;
        }

        return $this->contains($absolutePath . DIRECTORY_SEPARATOR, $scanRoot);
    }

    public function contains(string $path, string $scanRoot): bool
    {
        $relative = str_starts_with($path, $scanRoot)
            ? substr($path, strlen($scanRoot))
            : $path;

        foreach (self::ALWAYS_EXCLUDED_SEGMENTS as $segment) {
            if (str_contains($relative, $segment)) {
                return true;
            }
        }

        foreach ($this->absolutePrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        if ($this->destDirBasename !== '') {
            $destPrefix = rtrim($scanRoot, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . $this->destDirBasename
                . DIRECTORY_SEPARATOR;

            if (str_starts_with($path, $destPrefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $directories
     *
     * @return list<string>
     */
    private function normalizePrefixes(array $directories): array
    {
        $prefixes = [];
        foreach ($directories as $dir) {
            if ($dir === '') {
                continue;
            }

            $real = realpath($dir);
            $resolved = $real !== false ? $real : $dir;
            $prefixes[] = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return $prefixes;
    }
}
