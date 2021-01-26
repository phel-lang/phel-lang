<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\Command\Repl\ColorStyle;
use Phel\Command\Repl\ColorStyleInterface;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Exceptions\Extractor\FilePositionExtractor;
use Phel\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Exceptions\Extractor\SourceMapExtractor;
use Phel\Exceptions\Printer\ExceptionArgsPrinter;
use Phel\Exceptions\Printer\ExceptionArgsPrinterInterface;
use Phel\Lang\FnInterface;
use Phel\Lang\SourceLocation;
use Phel\Printer\Printer;
use ReflectionClass;
use Throwable;

final class TextExceptionPrinter implements ExceptionPrinterInterface
{
    private ExceptionArgsPrinterInterface $exceptionArgsPrinter;
    private ColorStyleInterface $style;
    private MungeInterface $munge;
    private FilePositionExtractorInterface $filePositionExtractor;

    public function __construct(
        ExceptionArgsPrinterInterface $exceptionArgsPrinter,
        ColorStyleInterface $style,
        MungeInterface $munge,
        FilePositionExtractorInterface $filePositionExtractor
    ) {
        $this->exceptionArgsPrinter = $exceptionArgsPrinter;
        $this->style = $style;
        $this->munge = $munge;
        $this->filePositionExtractor = $filePositionExtractor;
    }

    public static function create(): self
    {
        return new self(
            new ExceptionArgsPrinter(Printer::readable()),
            ColorStyle::withStyles(),
            new Munge(),
            new FilePositionExtractor(new SourceMapExtractor())
        );
    }

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void
    {
        echo $this->getExceptionString($e, $codeSnippet);
    }

    public function getExceptionString(PhelCodeException $e, CodeSnippet $codeSnippet): string
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
            if (strlen($line) > 0) {
                $str .= '| ' . $line . PHP_EOL;
            } else {
                $str .= '|' . PHP_EOL;
            }

            $eStartLine = $errorStartLocation->getLine();
            if ($eStartLine === $errorEndLocation->getLine()
                && $eStartLine === $index + $codeSnippet->getStartLocation()->getLine()
            ) {
                $str .= $this->underliningErrorPointer($endLineLength, $errorStartLocation, $errorEndLocation);
            }
        }

        if ($e->getPrevious()) {
            $str .= PHP_EOL . PHP_EOL . 'Caused by:' . PHP_EOL;
            $str .= $e->getPrevious()->getTraceAsString();
            $str .= PHP_EOL;
        }

        return $str;
    }

    public function printStackTrace(Throwable $e): void
    {
        echo $this->getStackTraceString($e);
    }

    public function getStackTraceString(Throwable $e): string
    {
        $str = '';
        $type = get_class($e);
        $msg = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        $pos = $this->filePositionExtractor->getOriginal($errorFile, $errorLine);

        $str .= $this->style->blue("$type: $msg" . PHP_EOL);
        $str .= "in {$pos->filename()}:{$pos->line()} (gen: $errorFile:$errorLine)" . PHP_EOL . PHP_EOL;

        foreach ($e->getTrace() as $i => $frame) {
            $class = $frame['class'] ?? null;
            $file = $frame['file'];
            $line = $frame['line'];

            if ($class) {
                $rf = new ReflectionClass($class);
                if ($rf->implementsInterface(FnInterface::class)) {
                    $fnName = $this->munge->decodeNs($rf->getConstant('BOUND_TO'));
                    $argString = $this->exceptionArgsPrinter->parseArgsAsString($frame['args'] ?? []);
                    $pos = $this->filePositionExtractor->getOriginal($file, $line);
                    $str .= "#$i {$pos->filename()}:{$pos->line()} (gen: $file:$line) : ($fnName$argString)" . PHP_EOL;

                    continue;
                }
            }

            $class = $class ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'];
            $argString = $this->exceptionArgsPrinter->buildPhpArgsString($frame['args'] ?? []);
            $str .= "#$i $file($line): $class$type$fn($argString)" . PHP_EOL;
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
