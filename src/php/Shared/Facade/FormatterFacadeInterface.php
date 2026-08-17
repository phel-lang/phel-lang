<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Phel\Shared\CompilerConstants;
use Phel\Shared\Formatter\FormatResult;
use Symfony\Component\Console\Output\OutputInterface;

interface FormatterFacadeInterface
{
    /**
     * Formats every `.phel` file found under $paths.
     *
     * Files that cannot be formatted (unreadable path, or source that fails to
     * lex/parse) are reported on $output and collected in
     * {@see FormatResult::failedPaths()} instead of aborting the batch, so
     * callers can still exit non-zero.
     *
     * `$exclude` are `fnmatch` globs; a discovered file matching one, by its
     * path as given or relative to the working directory, is skipped and
     * lands in neither list (#3233).
     *
     * @param list<string> $paths
     * @param list<string> $exclude
     */
    public function format(array $paths, OutputInterface $output, bool $dryRun = false, array $exclude = []): FormatResult;

    /**
     * Format a Phel source string in memory without touching the filesystem.
     *
     * $uri is only the label reported in lex/parse errors; nothing is read from
     * or written to it.
     */
    public function formatString(string $source, string $uri = CompilerConstants::DEFAULT_SOURCE): string;
}
