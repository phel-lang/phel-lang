<?php

namespace Phel\Exceptions;

use Phel\Stream\CodeSnippet;

class HtmlExceptionPrinter implements ExceptionPrinter {

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet) {
        $firstLine = $e->getStartLocation()->getLine();

        echo '<p>' . $e->getMessage() . "<br/>";
        echo "in <em>" . $e->getStartLocation()->getFile() . ':' . $firstLine . "</em></p>";

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string) $codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string) $codeSnippet->getStartLocation()->getLine());
        echo "<pre><code>";
        foreach ($lines as $index => $line) {
            echo str_pad($firstLine + $index, $padLength, ' ', STR_PAD_LEFT);
            echo "| ";
            echo htmlspecialchars($line);
            echo "\n";

            if ($e->getStartLocation()->getLine() == $e->getEndLocation()->getLine()) {
                if ($e->getStartLocation()->getLine() == $index + $codeSnippet->getStartLocation()->getLine()) {
                    echo str_repeat(' ', $endLineLength + 1 + $e->getStartLocation()->getColumn());
                    echo str_repeat('^', $e->getEndLocation()->getColumn() - $e->getStartLocation()->getColumn() + 1);
                    echo "\n";
                }
            }
        }

        echo "</pre></code>";

        if ($e->getPrevious()) {
            echo "<p>Caused by:</p>";
            echo "<pre><code>";
            echo $e->getPrevious()->getTraceAsString();
            echo "</code></pre>";
        }
    }
};