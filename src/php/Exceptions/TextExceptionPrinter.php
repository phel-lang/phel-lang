<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\Command\Repl\ColorStyle;
use Phel\Command\Repl\ColorStyleInterface;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Exceptions\ExceptionPrinter\ExceptionPrinterTrait;
use Phel\Exceptions\Extractor\CommentExtractor;
use Phel\Exceptions\Extractor\FilePositionExtractor;
use Phel\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Lang\FnInterface;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use ReflectionClass;
use Throwable;

final class TextExceptionPrinter implements ExceptionPrinterInterface
{
    use ExceptionPrinterTrait;

    private PrinterInterface $printer;
    private ColorStyleInterface $style;
    private MungeInterface $munge;
    private FilePositionExtractorInterface $filePositionExtractor;

    public static function readableWithStyle(): self
    {
        return new self(
            Printer::readable(),
            ColorStyle::withStyles(),
            new Munge(),
            new FilePositionExtractor(new CommentExtractor())
        );
    }

    public function __construct(
        PrinterInterface $printer,
        ColorStyleInterface $style,
        MungeInterface $munge,
        FilePositionExtractorInterface $filePositionExtractor
    ) {
        $this->printer = $printer;
        $this->style = $style;
        $this->munge = $munge;
        $this->filePositionExtractor = $filePositionExtractor;
    }

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void
    {
        $eStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $eEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $eFirstLine = $eStartLocation->getLine();

        echo $this->style->blue($e->getMessage()) . "\n";
        echo 'in ' . $eStartLocation->getFile() . ':' . $eFirstLine . "\n\n";

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string) $codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string) $codeSnippet->getStartLocation()->getLine());
        foreach ($lines as $index => $line) {
            echo str_pad((string) ($eFirstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            echo '| ';
            echo $line;
            echo "\n";

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
        $generatedLine = $e->getFile();
        $generatedColumn = $e->getLine();
        $pos = $this->filePositionExtractor->getOriginal($generatedLine, $generatedColumn);

        echo $this->style->blue("$type: $msg\n");
        echo "in {$pos->fileName()}:{$pos->line()} (gen: $generatedLine:$generatedColumn)\n\n";

        foreach ($e->getTrace() as $i => $frame) {
            $class = $frame['class'] ?? null;
            $generatedLine = $frame['file'];
            $generatedColumn = $frame['line'];

            if ($class) {
                $rf = new ReflectionClass($class);
                if ($rf->implementsInterface(FnInterface::class)) {
                    $fnName = $this->munge->decodeNs($rf->getConstant('BOUND_TO'));
                    $argString = $this->parseArgsAsString($this->printer, $frame['args']);
                    $pos = $this->filePositionExtractor->getOriginal($generatedLine, $generatedColumn);
                    echo "#$i {$pos->fileName()}:{$pos->line()} (gen: $generatedLine:$generatedColumn) : ($fnName$argString)\n";

                    continue;
                }
            }

            $class = $class ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'];
            $argString = $this->buildPhpArgsString($frame['args']);
            echo "#$i $generatedLine($generatedColumn): $class$type$fn($argString)\n";
        }
    }
}
