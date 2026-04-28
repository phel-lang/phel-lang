<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

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
     * Agent tooling (Claude Code, Codex, etc.) drops repo clones under a
     * `worktrees/` directory whose `src/phel/` would shadow real sources.
     * Agent config directories can contain examples, scripts, or worktrees
     * whose Phel files must not leak into the host repo's scan.
     */
    private const array ALWAYS_EXCLUDED_SEGMENTS = [
        DIRECTORY_SEPARATOR . 'worktrees' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'agents' . DIRECTORY_SEPARATOR,
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
