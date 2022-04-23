<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain;

use Phel\Command\CommandFacadeInterface;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class PathsFormatter
{
    private CommandFacadeInterface $commandFacade;
    private FormatterInterface $formatter;
    private PathFilterInterface $pathFilter;

    public function __construct(
        CommandFacadeInterface $commandFacade,
        FormatterInterface $formatter,
        PathFilterInterface $pathFilter
    ) {
        $this->commandFacade = $commandFacade;
        $this->formatter = $formatter;
        $this->pathFilter = $pathFilter;
    }

    /**
     * @return list<string>
     */
    public function format(array $paths, OutputInterface $output): array
    {
        $successfulFormattedFilePaths = [];

        foreach ($this->pathFilter->filterPaths($paths) as $path) {
            try {
                $wasFormatted = $this->formatFile($path);
                if ($wasFormatted) {
                    $successfulFormattedFilePaths[] = $path;
                }
            } catch (AbstractParserException $e) {
                $this->commandFacade->writeLocatedException($output, $e, $e->getCodeSnippet());
            } catch (Throwable $e) {
                $this->commandFacade->writeStackTrace($output, $e);
            }
        }

        return $successfulFormattedFilePaths;
    }

    /**
     * @throws LexerValueException
     * @throws ZipperException
     * @throws AbstractParserException
     *
     * @return bool True if the file was formatted. False if the file wasn't altered because it was already formatted.
     */
    private function formatFile(string $filename): bool
    {
        if (is_dir($filename)) {
            throw new RuntimeException(sprintf('"%s" is a directory but needs to be a file path', $filename));
        }

        if (!is_file($filename)) {
            throw new RuntimeException(sprintf('File path "%s" not found', $filename));
        }

        // TODO: Consider creating some `FileIoInterface` to do this IO actions
        $code = file_get_contents($filename);
        $formattedCode = $this->formatter->format($code, $filename);
        file_put_contents($filename, $formattedCode);

        return (bool)strcmp($formattedCode, $code);
    }
}
