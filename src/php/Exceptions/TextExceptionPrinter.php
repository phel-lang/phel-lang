<?php

namespace Phel\Exceptions;

use Phel\Stream\CodeSnippet;

class TextExceptionPrinter implements ExceptionPrinter {

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet) {
        $firstLine = $e->getStartLocation()->getLine();

        echo $e->getMessage() . "\n";
        echo "in " . $e->getStartLocation()->getFile() . ':' . $firstLine . "\n\n";

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string) $codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string) $codeSnippet->getStartLocation()->getLine());
        foreach ($lines as $index => $line) {
            echo str_pad($firstLine + $index, $padLength, ' ', STR_PAD_LEFT);
            echo "| ";
            echo $line;
            echo "\n";

            if ($e->getStartLocation()->getLine() == $e->getEndLocation()->getLine()) {
                if ($e->getStartLocation()->getLine() == $index + $codeSnippet->getStartLocation()->getLine()) {
                    echo str_repeat(' ', $endLineLength + 2 + $e->getStartLocation()->getColumn());
                    echo str_repeat('^', $e->getEndLocation()->getColumn() - $e->getStartLocation()->getColumn());
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
};