<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Format\PathFilterInterface;
use Phel\Command\Shared\CommandIoInterface;
use Phel\Formatter\FormatterInterface;

final class FormatCommand
{
    public const COMMAND_NAME = 'fmt';

    private FormatterInterface $formatter;
    private CommandIoInterface $io;
    private PathFilterInterface $pathFilter;

    /** @var list<string> */
    private array $successfulFormattedFilePaths = [];

    public function __construct(
        FormatterInterface $formatter,
        CommandIoInterface $io,
        PathFilterInterface $pathFilter
    ) {
        $this->formatter = $formatter;
        $this->io = $io;
        $this->pathFilter = $pathFilter;
    }

    /**
     * @param list<string> $paths
     */
    public function run(array $paths): void
    {
        foreach ($this->pathFilter->filterPaths($paths) as $path) {
            $wasFormatted = $this->formatter->formatFile($path);

            if ($wasFormatted) {
                $this->successfulFormattedFilePaths[] = $path;
            }
        }

        $this->printResult();
    }

    private function printResult(): void
    {
        if (empty($this->successfulFormattedFilePaths)) {
            $this->io->output('No files were formatted.' . PHP_EOL);
        } else {
            $this->io->output('Formatted files:' . PHP_EOL);

            foreach ($this->successfulFormattedFilePaths as $k => $filePath) {
                $this->io->output(sprintf('  %d) %s %s', $k + 1, $filePath, PHP_EOL));
            }
        }
    }
}
