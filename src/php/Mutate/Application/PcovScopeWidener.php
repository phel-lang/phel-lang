<?php

declare(strict_types=1);

namespace Phel\Mutate\Application;

use function extension_loaded;

use const DIRECTORY_SEPARATOR;

/**
 * The `-d` arguments a worker subprocess needs before pcov can see the PHP
 * that Phel compiles.
 *
 * pcov instruments only files whose path starts with `pcov.directory`, which
 * defaults to `<cwd>/src` (then `lib`, `app`, `.`). Phel executes its compiled
 * PHP out of the system temp directory, so under that default a worker
 * collects nothing: every mutant reads as "not covered" and the run scores
 * work it never did (#3270). `pcov.directory` is PHP_INI_SYSTEM, so only a
 * fresh process can widen it.
 *
 * @internal
 */
final readonly class PcovScopeWidener
{
    public function __construct(
        private bool $pcovLoaded,
    ) {}

    public static function detect(): self
    {
        return new self(extension_loaded('pcov'));
    }

    /**
     * @return list<string>
     */
    public function iniArguments(): array
    {
        if (!$this->pcovLoaded) {
            return [];
        }

        // pcov resolves the directory through realpath but matches it against the
        // path each file was opened with; the temp directory is reached through a
        // symlink on macOS, so the filesystem root is the only prefix that matches
        // both. Nothing outside the project survives the mapping back to `.phel`.
        return ['-d', 'pcov.directory=' . DIRECTORY_SEPARATOR];
    }
}
