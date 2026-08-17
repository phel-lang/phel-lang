<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain;

use function fnmatch;
use function getcwd;
use function is_string;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;
use const FNM_NOESCAPE;

/**
 * The paths `phel format` leaves alone: `--exclude` globs and the
 * `format-exclude` config key, unioned. A pattern is `fnmatch`ed against the
 * path as it was discovered and against the same path relative to the
 * working directory, so `src/gen/*` skips a generated tree whether `phel
 * format` was given `src` or an absolute path. `*` spans directories, as it
 * does for `phel lint`'s exclude, so `src/*_data.phel` reaches any depth.
 *
 * @internal
 */
final readonly class ExcludePatterns
{
    /**
     * @param list<string> $patterns
     */
    public function __construct(
        private array $patterns,
        private string $workingDirectory,
    ) {}

    /**
     * @param list<string> $patterns
     */
    public static function fromWorkingDirectory(array $patterns): self
    {
        $cwd = getcwd();

        return new self($patterns, is_string($cwd) ? $cwd : '');
    }

    public function matches(string $path): bool
    {
        if ($this->patterns === []) {
            return false;
        }

        $relative = $this->relativeToWorkingDirectory($path);
        foreach ($this->patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            if (fnmatch($pattern, $path, FNM_NOESCAPE) || fnmatch($pattern, $relative, FNM_NOESCAPE)) {
                return true;
            }
        }

        return false;
    }

    private function relativeToWorkingDirectory(string $path): string
    {
        if ($this->workingDirectory === '') {
            return $path;
        }

        $root = rtrim($this->workingDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
