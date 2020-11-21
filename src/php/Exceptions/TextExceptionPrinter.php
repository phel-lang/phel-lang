<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\CodeSnippet;
use Phel\Command\Repl\ColorStyle;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapConsumer;
use Phel\Lang\IFn;
use Phel\Printer;
use ReflectionClass;
use Throwable;

final class TextExceptionPrinter implements ExceptionPrinterInterface
{
    private Printer $printer;
    private ColorStyle $style;
    private Munge $munge;

    public static function readableWithStyle(): self
    {
        return new self(Printer::readable(), ColorStyle::withStyles(), new Munge());
    }

    private function __construct(Printer $printer, ColorStyle $style, Munge $munge)
    {
        $this->printer = $printer;
        $this->style = $style;
        $this->munge = $munge;
    }

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void
    {
        $eStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $eEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $firstLine = $eStartLocation->getLine();

        echo $this->style->blue($e->getMessage()) . "\n";
        echo 'in ' . $eStartLocation->getFile() . ':' . $firstLine . "\n\n";

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string) $codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string) $codeSnippet->getStartLocation()->getLine());
        foreach ($lines as $index => $line) {
            echo str_pad((string) ($firstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            echo '| ';
            echo $line;
            echo "\n";

            if ($eStartLocation->getLine() === $eEndLocation->getLine()) {
                if ($eStartLocation->getLine() === $index + $codeSnippet->getStartLocation()->getLine()) {
                    echo str_repeat(' ', $endLineLength + 2 + $eStartLocation->getColumn());
                    echo $this->style->red(str_repeat('^', $eEndLocation->getColumn() - $eStartLocation->getColumn()));
                    echo "\n";
                }
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
        [$file, $line] = $this->getOriginalFilePosition($generatedLine, $generatedColumn);

        echo $this->style->blue("$type: $msg\n");
        echo "in $file:$line (gen: $generatedLine:$generatedColumn)\n\n";

        foreach ($e->getTrace() as $i => $frame) {
            $class = $frame['class'] ?? null;
            $generatedLine = $frame['file'];
            $generatedColumn = $frame['line'];

            if ($class) {
                $rf = new ReflectionClass($class);
                if ($rf->implementsInterface(IFn::class)) {
                    $fnName = $this->munge->decodeNs($rf->getConstant('BOUND_TO'));
                    $argParts = [];
                    foreach ($frame['args'] as $arg) {
                        $argParts[] = $this->printer->print($arg);
                    }
                    $argString = implode(' ', $argParts);
                    if (count($argParts) > 0) {
                        $argString = ' ' . $argString;
                    }

                    [$file, $line] = $this->getOriginalFilePosition($generatedLine, $generatedColumn);

                    echo "#$i $file:$line (gen: $generatedLine:$generatedColumn) : ($fnName$argString)\n";

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

    /**
     * @psalm-return array{0:string, 1:int}
     */
    private function getOriginalFilePosition(string $filename, int $line): array
    {
        $f = fopen($filename, 'r');
        $phpPrefix = fgets($f);
        $fileNameComment = fgets($f);
        $sourceMapComment = fgets($f);

        $originalFile = $filename;
        $originalLine = $line;

        if ($fileNameComment
            && $fileNameComment[0] === '/'
            && $fileNameComment[1] === '/'
            && $fileNameComment[2] === ' '
        ) {
            $originalFile = trim(substr($fileNameComment, 3));

            if ($sourceMapComment
                && $sourceMapComment[0] === '/'
                && $sourceMapComment[1] === '/'
                && $sourceMapComment[2] === ' '
            ) {
                $mapping = trim(substr($sourceMapComment, 3));

                $sourceMapConsumer = new SourceMapConsumer($mapping);
                $originalLine = ($sourceMapConsumer->getOriginalLine($line - 1)) ?: $line;
            }
        }

        return [$originalFile, (int)$originalLine];
    }

    private function buildPhpArgsString(array $args): string
    {
        $result = [];
        foreach ($args as $arg) {
            $result[] = $this->buildPhpArg($arg);
        }

        return implode(', ', $result);
    }

    /**
     * Converts a PHP type to a string.
     *
     * @param mixed $arg The argument
     */
    private function buildPhpArg($arg): string
    {
        if (is_null($arg)) {
            return 'NULL';
        }

        if (is_string($arg)) {
            $s = $arg;
            if (strlen($s) > 15) {
                $s = substr($s, 0, 15) . '...';
            }
            return "'" . $s . "'";
        }

        if (is_bool($arg)) {
            return ($arg) ? 'true' : 'false';
        }

        if (is_resource($arg)) {
            return 'Resource id #' . ((string) $arg);
        }

        if (is_array($arg)) {
            return 'Array';
        }

        if (is_object($arg)) {
            return 'Object(' . get_class($arg) . ')';
        }

        return (string) $arg;
    }
}
