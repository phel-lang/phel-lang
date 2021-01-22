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
        $eStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $eEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $eFirstLine = $eStartLocation->getLine();

        echo $this->style->blue($e->getMessage()) . "\n";
        echo 'in ' . $eStartLocation->getFile() . ':' . $eFirstLine . "\n\n";

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string)$codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string)$codeSnippet->getStartLocation()->getLine());
        foreach ($lines as $index => $line) {
            echo str_pad((string)($eFirstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            echo '| ', $line, "\n";

            $eStartLine = $eStartLocation->getLine();
            if ($eStartLine === $eEndLocation->getLine()
                && $eStartLine === $index + $codeSnippet->getStartLocation()->getLine()
            ) {
                echo str_repeat(' ', $endLineLength + 2 + $eStartLocation->getColumn());
                echo $this->style->red(str_repeat('^', $eEndLocation->getColumn() - $eStartLocation->getColumn()));
                echo "\n";
            }
        }

        if ($e->getPrevious()) {
            echo "\n\nCaused by:\n";
            echo $e->getPrevious()->getTraceAsString();
            echo "\n";
        }
    }

    public function printStackTrace(Throwable $e): void
    {
        $type = get_class($e);
        $msg = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        $pos = $this->filePositionExtractor->getOriginal($errorFile, $errorLine);

        echo $this->style->blue("$type: $msg\n");
        echo "in {$pos->filename()}:{$pos->line()} (gen: $errorFile:$errorLine)\n\n";

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
                    echo "#$i {$pos->filename()}:{$pos->line()} (gen: $file:$line) : ($fnName$argString)\n";

                    continue;
                }
            }

            $class = $class ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'];
            $argString = $this->exceptionArgsPrinter->buildPhpArgsString($frame['args'] ?? []);
            echo "#$i $file($line): $class$type$fn($argString)\n";
        }
    }
}
