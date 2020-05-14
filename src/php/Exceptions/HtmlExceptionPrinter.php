<?php

namespace Phel\Exceptions;

use Phel\Stream\CodeSnippet;

class HtmlExceptionPrinter implements ExceptionPrinter {

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void {
        $eStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $eEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $firstLine = $eStartLocation->getLine();

        echo '<p>' . $e->getMessage() . "<br/>";
        echo "in <em>" . $eStartLocation->getFile() . ':' . $firstLine . "</em></p>";

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string) $codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string) $codeSnippet->getStartLocation()->getLine());
        echo "<pre><code>";
        foreach ($lines as $index => $line) {
            echo str_pad((string) ($firstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            echo "| ";
            echo htmlspecialchars($line);
            echo "\n";

            if ($eStartLocation->getLine() == $eEndLocation->getLine()) {
                if ($eStartLocation->getLine() == $index + $codeSnippet->getStartLocation()->getLine()) {
                    echo str_repeat(' ', $endLineLength + 2 + $eStartLocation->getColumn());
                    echo str_repeat('^', $eEndLocation->getColumn() - $eStartLocation->getColumn());
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