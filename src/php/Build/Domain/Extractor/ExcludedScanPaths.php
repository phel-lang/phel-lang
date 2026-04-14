<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

/**
 * Prefixes that should be skipped during a recursive namespace scan. Combines
 * pre-resolved absolute directories with a basename that prunes
 * `<scan_root>/<basename>/` per walk, so a build output never shadows real
 * sources regardless of which scan root the caller passes in.
 */
final readonly class ExcludedScanPaths
{
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
