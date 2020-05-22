<?php

namespace Phel\Exceptions;

use Exception;
use Phel\Lang\IFn;
use Phel\Printer;
use Phel\Stream\CodeSnippet;
use ReflectionClass;

class TextExceptionPrinter implements ExceptionPrinter {

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void {
        $eStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $eEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $firstLine = $eStartLocation->getLine();

        echo $e->getMessage() . "\n";
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
                    echo str_repeat('^', $eEndLocation->getColumn() - $eStartLocation->getColumn());
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

    public function printStackTrace(Exception $e): void {
        $printer = new Printer();

        $type = get_class($e);
        $msg = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();

        echo "$type: $msg in $file:$line\n";

        foreach ($e->getTrace() as $i => $frame) {
            $class = isset($frame['class']) ? $frame['class'] : null;
            $file = $frame['file'];
            $line = $frame['line'];

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

                    echo "#$i $file($line): ($fnName$argString)\n";

                    continue;
                }
            }

            $class = $class ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'];
            $argString = $this->buildPhpArgsString($frame['args']);
            echo "#$i $file($line): $class$type$fn($argString)\n";
        }
    }

    private function buildPhpArgsString($args) {
        $result = [];
        foreach ($args as $arg) {
            $result[] = $this->buildPhpArg($arg);
        }

        return implode(", ", $result);
    }

    private function buildPhpArg($arg) {
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
            return "Resource id #" . $arg;
        }
        
        if (is_array($arg)) {
            return "Array";
        }
        
        if (is_object($arg)) {
            return 'Object(' . get_class($arg) . ')';
        }
        
        return (string) $arg;
    }
};