<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

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
     * @param list<string> $paths
     */
    public function format(array $paths, OutputInterface $output, bool $dryRun = false): FormatResult;
}
