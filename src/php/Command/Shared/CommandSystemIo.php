<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Runtime\Exceptions\ExceptionPrinterInterface;
use RuntimeException;
use Throwable;

final class CommandSystemIo implements CommandIoInterface
{
    private ExceptionPrinterInterface $exceptionPrinter;

    public function __construct(ExceptionPrinterInterface $exceptionPrinter)
    {
        $this->exceptionPrinter = $exceptionPrinter;
    }

    public function createDirectory(string $directory): void
    {
        if (!mkdir($directory, $permissions = 0777, $recursive = true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
    }

    public function fileGetContents(string $filename): string
    {
        if (is_dir($filename)) {
            throw new RuntimeException(sprintf('"%s" is a directory but needs to be a file path', $filename));
        }

        if (!is_file($filename)) {
            throw new RuntimeException(sprintf('File path "%s" not found', $filename));
        }

        return file_get_contents($filename);
    }

    public function filePutContents(string $filename, string $content): void
    {
        file_put_contents($filename, $content);
    }

    public function writeStackTrace(Throwable $e): void
    {
        $this->writeln($this->exceptionPrinter->getStackTraceString($e));

        if ($e->getPrevious()) {
            $this->writeln();
            $this->writeln('Caused by');
            $this->writeStackTrace($e->getPrevious());
        }
    }

    public function writeLocatedException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void
    {
        $this->writeln($this->exceptionPrinter->getExceptionString($e, $codeSnippet));
    }

    public function writeln(string $string = ''): void
    {
        print $string . PHP_EOL;
    }
}
