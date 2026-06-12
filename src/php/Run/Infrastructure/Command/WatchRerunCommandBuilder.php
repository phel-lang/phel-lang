<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use function array_slice;

/**
 * Builds the shell command `phel test --watch` re-executes on every change:
 * the original invocation with the `--watch` flag removed, fully escaped.
 */
final readonly class WatchRerunCommandBuilder
{
    public function __construct(
        private string $phpBinary = PHP_BINARY,
    ) {}

    /**
     * @param list<string> $argv the original process argv (argv[0] is ignored in favor of `$scriptFilename`)
     */
    public function build(array $argv, string $scriptFilename): string
    {
        $parts = [escapeshellarg($this->phpBinary), escapeshellarg($scriptFilename)];
        foreach (array_slice($argv, 1) as $argument) {
            if ($argument !== '--' . TestCommandOptionParser::OPT_WATCH) {
                $parts[] = escapeshellarg($argument);
            }
        }

        return implode(' ', $parts);
    }
}
