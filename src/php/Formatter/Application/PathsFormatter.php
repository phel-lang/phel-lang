<?php

declare(strict_types=1);

namespace Phel\Formatter\Application;

use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Formatter\Domain\Exception\FilePathException;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Formatter\Domain\PathFilterInterface;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Formatter\Infrastructure\IO\FileIoInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final readonly class PathsFormatter
{
    public function __construct(
        private CommandFacadeInterface $commandFacade,
        private FormatterInterface $formatter,
        private PathFilterInterface $pathFilter,
        private FileIoInterface $fileIo,
    ) {}

    /**
     * @return list<string> paths whose contents changed (or would change under $dryRun)
     */
    public function format(array $paths, OutputInterface $output, bool $dryRun = false): array
    {
        $formattedFilePaths = [];

        foreach ($this->pathFilter->filterPaths($paths) as $path) {
            try {
                $wasFormatted = $this->formatFile($path, $dryRun);
                if ($wasFormatted) {
                    $formattedFilePaths[] = $path;
                }
            } catch (AbstractParserException $e) {
                $this->commandFacade->writeLocatedException($output, $e, $e->getCodeSnippet());
            } catch (Throwable $e) {
                $this->commandFacade->writeStackTrace($output, $e);
            }
        }

        return $formattedFilePaths;
    }

    /**
     * @throws FilePathException
     * @throws LexerValueException
     * @throws ZipperException
     * @throws AbstractParserException
     *
     * @return bool True when the file's contents differ from the formatted output.
     *              Under $dryRun the file is left untouched.
     */
    private function formatFile(string $filename, bool $dryRun): bool
    {
        $this->fileIo->checkIfValid($filename);

        $code = $this->fileIo->getContents($filename);
        $formattedCode = $this->formatter->format($code, $filename);
        $changed = (bool) strcmp($formattedCode, $code);

        if ($changed && !$dryRun) {
            $this->fileIo->putContents($filename, $formattedCode);
        }

        return $changed;
    }
}
