<?php

namespace Phel\Exceptions;

use Phel\Lang\IFn;
use Phel\Printer;
use Phel\CodeSnippet;
use Phel\SourceMap\SourceMapConsumer;
use ReflectionClass;
use Throwable;

class TextExceptionPrinter implements ExceptionPrinter
{
    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void
    {
        $eStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $eEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $firstLine = $eStartLocation->getLine();

        echo $this->color($e->getMessage(), 'blue') . "\n";
        echo "in " . $eStartLocation->getFile() . ':' . $firstLine . "\n\n";

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string) $codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string) $codeSnippet->getStartLocation()->getLine());
        foreach ($lines as $index => $line) {
            echo str_pad((string) ($firstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            echo "| ";
            echo $line;
            echo "\n";

            if ($eStartLocation->getLine() == $eEndLocation->getLine()) {
                if ($eStartLocation->getLine() == $index + $codeSnippet->getStartLocation()->getLine()) {
                    echo str_repeat(' ', $endLineLength + 2 + $eStartLocation->getColumn());
                    echo $this->color(str_repeat('^', $eEndLocation->getColumn() - $eStartLocation->getColumn()), 'red');
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
        $printer = new Printer();

        $type = get_class($e);
        $msg = $e->getMessage();
        $generatedLine = $e->getFile();
        $generetedColumn = $e->getLine();
        list($file, $line) = $this->getOriginalFilePosition($generatedLine, $generetedColumn);

        echo $this->color("$type: $msg\n", "blue");
        echo "in $file:$line (gen: $generatedLine:$generetedColumn)\n\n";

        foreach ($e->getTrace() as $i => $frame) {
            $class = $frame['class'] ?? null;
            $generatedLine = $frame['file'];
            $generetedColumn = $frame['line'];

            if ($class) {
                $rf = new ReflectionClass($class);
                if ($rf->implementsInterface(IFn::class)) {
                    $fnName = $rf->getConstant('BOUND_TO');
                    $argParts = [];
                    foreach ($frame['args'] as $arg) {
                        $argParts[] = $printer->print($arg, true);
                    }
                    $argString = implode(' ', $argParts);
                    if (count($argParts) > 0) {
                        $argString = " " . $argString;
                    }

                    list($file, $line) = $this->getOriginalFilePosition($generatedLine, $generetedColumn);

                    echo "#$i $file:$line (gen: $generatedLine:$generetedColumn) : ($fnName$argString)\n";

                    continue;
                }
            }

            $class = $class ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'];
            $argString = $this->buildPhpArgsString($frame['args']);
            echo "#$i $generatedLine($generetedColumn): $class$type$fn($argString)\n";
        }
    }

    private function getOriginalFilePosition($filename, $line)
    {
        $f = fopen($filename, 'r');
        $phpPrefix = fgets($f);
        $fileNameComment = fgets($f);
        $sourceMapComment = fgets($f);

        $originalFile = $filename;
        $originalLine = $line;

        if ($fileNameComment
            && $fileNameComment[0] == "/"
            && $fileNameComment[1] == "/"
            && $fileNameComment[2] == " "
        ) {
            $originalFile = trim(substr($fileNameComment, 3));

            if ($sourceMapComment
                && $sourceMapComment[0] == "/"
                && $sourceMapComment[1] == "/"
                && $sourceMapComment[2] == " "
            ) {
                $mapping = trim(substr($sourceMapComment, 3));

                $sourceMapConsumer = new SourceMapConsumer($mapping);
                $originalLine = ($sourceMapConsumer->getOriginalLine($line - 1)) ?: $line;
            }
        }

        return [$originalFile, $originalLine];
    }

    private function buildPhpArgsString($args)
    {
        $result = [];
        foreach ($args as $arg) {
            $result[] = $this->buildPhpArg($arg);
        }

        return implode(", ", $result);
    }

    private function buildPhpArg($arg)
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
            return ($arg) ? "true" : "false";
        }

        if (is_resource($arg)) {
            return "Resource id #" . ((string) $arg);
        }

        if (is_array($arg)) {
            return "Array";
        }

        if (is_object($arg)) {
            return 'Object(' . get_class($arg) . ')';
        }

        return (string) $arg;
    }

    private function color($text = '', $color = null)
    {
        $styles = array(
            'green'  => "\033[0;32m%s\033[0m",
            'red'    => "\033[31;31m%s\033[0m",
            'yellow' => "\033[33;33m%s\033[0m",
            'blue'   => "\033[33;34m%s\033[0m",
        );

        return sprintf($styles[$color] ?? "%s", $text);
    }
}
