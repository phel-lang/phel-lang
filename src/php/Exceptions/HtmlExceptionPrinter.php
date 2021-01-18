<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Exceptions\Printer\ExceptionArgsPrinter;
use Phel\Exceptions\Printer\ExceptionArgsPrinterInterface;
use Phel\Lang\FnInterface;
use Phel\Printer\Printer;
use ReflectionClass;
use Throwable;

final class HtmlExceptionPrinter implements ExceptionPrinterInterface
{
    private ExceptionArgsPrinterInterface $exceptionArgsPrinter;
    private MungeInterface $munge;

    private function __construct(
        ExceptionArgsPrinterInterface $exceptionArgsPrinter,
        MungeInterface $munge
    ) {
        $this->exceptionArgsPrinter = $exceptionArgsPrinter;
        $this->munge = $munge;
    }

    public static function create(): self
    {
        return new self(
            new ExceptionArgsPrinter(Printer::readable()),
            new Munge()
        );
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
            echo '| ', htmlspecialchars($line), "\n";

            $eStartLine = $eStartLocation->getLine();
            if ($eStartLine === $eEndLocation->getLine()
                && $eStartLine === $index + $codeSnippet->getStartLocation()->getLine()
            ) {
                echo str_repeat(' ', $endLineLength + 2 + $eStartLocation->getColumn());
                echo str_repeat('^', $eEndLocation->getColumn() - $eStartLocation->getColumn());
                echo "\n";
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
        $type = get_class($e);
        $msg = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();

        echo "<div>$type: $msg in $errorFile:$errorLine</div>";

        echo '<ul>';
        foreach ($e->getTrace() as $i => $frame) {
            $class = $frame['class'] ?? null;
            $file = $frame['file'];
            $line = $frame['line'];

            if ($class) {
                $rf = new ReflectionClass($class);
                if ($rf->implementsInterface(FnInterface::class)) {
                    $fnName = $this->munge->decodeNs($rf->getConstant('BOUND_TO'));
                    $argString = $this->exceptionArgsPrinter->parseArgsAsString($frame['args']);
                    echo "<li>#$i $file($line): ($fnName$argString)</li>";

                    continue;
                }
            }

            $class = $class ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'];
            $argString = $this->exceptionArgsPrinter->buildPhpArgsString($frame['args']);
            echo "<li>#$i $file($line): $class$type$fn($argString)</li>";
        }

        echo '</ul>';
    }
}
