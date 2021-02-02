<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Format\PathFilterInterface;
use Phel\Command\Shared\CommandIoInterface;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Formatter\FormatterInterface;
use Phel\Runtime\Exceptions\ExceptionPrinterInterface;

final class FormatCommand
{
    public const COMMAND_NAME = 'fmt';

    private FormatterInterface $formatter;
    private CommandIoInterface $io;
    private PathFilterInterface $pathFilter;
    private ExceptionPrinterInterface $exceptionPrinter;

    /** @var list<string> */
    private array $successfulFormattedFilePaths = [];

    public function __construct(
        FormatterInterface $formatter,
        CommandIoInterface $io,
        PathFilterInterface $pathFilter,
        ExceptionPrinterInterface $exceptionPrinter
    ) {
        $this->formatter = $formatter;
        $this->io = $io;
        $this->pathFilter = $pathFilter;
        $this->exceptionPrinter = $exceptionPrinter;
    }

    /**
     * @param list<string> $paths
     */
    public function run(array $paths): void
    {
        foreach ($this->pathFilter->filterPaths($paths) as $path) {
            try {
                $wasFormatted = $this->formatter->formatFile($path);
                if ($wasFormatted) {
                    $this->successfulFormattedFilePaths[] = $path;
                }
            } catch (AbstractParserException $e) {
                $this->printParserException($e);
            }
        }

        $this->printResult();
    }

    private function printResult(): void
    {
        if (empty($this->successfulFormattedFilePaths)) {
            $this->io->writeln('No files were formatted.');
        } else {
            $this->io->writeln('Formatted files:');

            foreach ($this->successfulFormattedFilePaths as $k => $filePath) {
                $this->io->writeln(sprintf('  %d) %s', $k + 1, $filePath));
            }
        }
    }

    private function printParserException(AbstractParserException $e): void
    {
        $this->io->writeln(
            $this->exceptionPrinter->getExceptionString(
                $e,
                $e->getCodeSnippet()
            )
        );
    }
}
