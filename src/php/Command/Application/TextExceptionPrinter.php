<?php

declare(strict_types=1);

namespace Phel\Command\Application;

use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinterInterface;
use Phel\Command\Domain\Exceptions\ExceptionPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Lang\FnInterface;
use Phel\Lang\SourceLocation;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use ReflectionClass;
use Throwable;

use function sprintf;
use function strlen;

use const PHP_EOL;

final readonly class TextExceptionPrinter implements ExceptionPrinterInterface
{
    public function __construct(
        private ExceptionArgsPrinterInterface $exceptionArgsPrinter,
        private ColorStyleInterface $style,
        private MungeInterface $munge,
        private FilePositionExtractorInterface $filePositionExtractor,
        private ErrorLogInterface $errorLog,
    ) {
    }

    public function printError(string $error): void
    {
        echo $error . PHP_EOL;
        $this->errorLog->writeln($error);
    }

    public function printException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void
    {
        $msg = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        echo sprintf('%s: %s', $e::class, $msg) . PHP_EOL;

        $this->errorLog->writeln($this->getExceptionString($e, $codeSnippet));
    }

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
    {
        $str = '';
        $errorStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $errorEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $errorFirstLine = $errorStartLocation->getLine();
        $codeFirstLine = $codeSnippet->getStartLocation()->getLine();

        $str .= $this->style->blue($e->getMessage()) . PHP_EOL;
        $str .= 'in ' . $errorStartLocation->getFile() . ':' . $errorFirstLine . PHP_EOL . PHP_EOL;

        $lines = explode(PHP_EOL, $codeSnippet->getCode());
        $endLineLength = strlen((string)$codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string)$codeFirstLine);

        foreach ($lines as $index => $line) {
            $str .= str_pad((string)($codeFirstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            if ($line !== '') {
                $str .= '| ' . $line . PHP_EOL;
            } else {
                $str .= '|' . PHP_EOL;
            }

            $eStartLine = $errorStartLocation->getLine();
            if ($eStartLine !== $errorEndLocation->getLine()) {
                continue;
            }

            if ($eStartLine !== $index + $codeSnippet->getStartLocation()->getLine()) {
                continue;
            }

            $str .= $this->underliningErrorPointer($endLineLength, $errorStartLocation, $errorEndLocation);
        }

        return $str;
    }

    public function printStackTrace(Throwable $e): void
    {
        $msg = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        echo sprintf('%s: %s', $e::class, $msg) . PHP_EOL;

        $this->errorLog->writeln($this->getStackTraceString($e));
    }

    public function getStackTraceString(Throwable $e): string
    {
        $str = '';
        $type = $e::class;
        $msg = $e->getPrevious()?->getMessage() ?? $e->getMessage();
        $errorFile = $e->getPrevious()?->getFile() ?? $e->getFile();
        $errorLine = $e->getPrevious()?->getLine() ?? $e->getLine();
        $pos = $this->filePositionExtractor->getOriginal($errorFile, $errorLine);

        $str .= $this->style->blue(sprintf('%s: %s', $type, $msg) . PHP_EOL);
        $str .= sprintf('in %s:%d (gen: %s:%d)', $pos->filename(), $pos->line(), $errorFile, $errorLine) . PHP_EOL . PHP_EOL;

        foreach ($e->getTrace() as $i => $frame) {
            $class = $frame['class'] ?? null;
            $file = $frame['file'] ?? 'unknown_file';
            $line = $frame['line'] ?? 0;

            if ($class !== null) {
                $rf = new ReflectionClass($class);
                if ($rf->implementsInterface(FnInterface::class)) {
                    $boundTo = $rf->getConstant('BOUND_TO');
                    $fnName = $boundTo !== false ? $this->munge->decodeNs($boundTo) : '__invoke';
                    $argString = $this->exceptionArgsPrinter->parseArgsAsString($frame['args'] ?? []);
                    $pos = $this->filePositionExtractor->getOriginal($file, $line);
                    $str .= sprintf('#%d %s:%d (gen: %s:%d) : (%s%s)', $i, $pos->filename(), $pos->line(), $file, $line, $fnName, $argString) . PHP_EOL;

                    continue;
                }
            }

            $class ??= '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'] ?? '';
            $argString = $this->exceptionArgsPrinter->buildPhpArgsString($frame['args'] ?? []);
            $str .= sprintf('#%d %s(%d): %s%s%s(%s)', $i, $file, $line, $class, $type, $fn, $argString) . PHP_EOL;
        }

        return $str;
    }

    private function underliningErrorPointer(int $lineLength, SourceLocation $start, SourceLocation $end): string
    {
        $preEmptyLines = str_repeat(' ', $lineLength + 2 + $start->getColumn());
        $pointer = str_repeat('^', max(1, $end->getColumn() - $start->getColumn()));
        $pointerInRed = $this->style->red($pointer);

        return $preEmptyLines . $pointerInRed . PHP_EOL;
    }
}
