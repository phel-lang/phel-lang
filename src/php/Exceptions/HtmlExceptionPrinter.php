<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\ReadModel\CodeSnippet;
use Phel\Lang\FnInterface;
use Phel\Printer\Printer;
use ReflectionClass;
use Throwable;

final class HtmlExceptionPrinter implements ExceptionPrinterInterface
{
    private Munge $munge;

    public static function create(): self
    {
        return new self(new Munge());
    }

    private function __construct(Munge $munge)
    {
        $this->munge = $munge;
    }

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void
    {
        $eStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $eEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $firstLine = $eStartLocation->getLine();

        echo '<p>' . $e->getMessage() . '<br/>';
        echo 'in <em>' . $eStartLocation->getFile() . ':' . $firstLine . '</em></p>';

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string)$codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string)$codeSnippet->getStartLocation()->getLine());
        echo '<pre><code>';
        foreach ($lines as $index => $line) {
            echo str_pad((string)($firstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            echo '| ';
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

        echo '</pre></code>';

        if ($e->getPrevious()) {
            echo '<p>Caused by:</p>';
            echo '<pre><code>';
            echo $e->getPrevious()->getTraceAsString();
            echo '</code></pre>';
        }
    }

    public function printStackTrace(Throwable $e): void
    {
        $printer = Printer::readable();
        $type = get_class($e);
        $msg = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();

        echo "<div>$type: $msg in $file:$line</div>";

        echo '<ul>';
        foreach ($e->getTrace() as $i => $frame) {
            $class = $frame['class'] ?? null;
            $file = $frame['file'];
            $line = $frame['line'];

            if ($class) {
                $rf = new ReflectionClass($class);
                if ($rf->implementsInterface(FnInterface::class)) {
                    $fnName = $this->munge->decodeNs($rf->getConstant('BOUND_TO'));
                    $argParts = [];
                    foreach ($frame['args'] as $arg) {
                        $argParts[] = $printer->print($arg);
                    }
                    $argString = implode(' ', $argParts);
                    if (count($argParts) > 0) {
                        $argString = ' ' . $argString;
                    }

                    echo "<li>#$i $file($line): ($fnName$argString)</li>";

                    continue;
                }
            }

            $class = $class ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'];
            $argString = $this->buildPhpArgsString($frame['args']);
            echo "<li>#$i $file($line): $class$type$fn($argString)</li>";
        }

        echo '</ul>';
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
            return 'Resource id #' . ((string)$arg);
        }

        if (is_array($arg)) {
            return 'Array';
        }

        if (is_object($arg)) {
            return 'Object(' . get_class($arg) . ')';
        }

        return (string)$arg;
    }
}
