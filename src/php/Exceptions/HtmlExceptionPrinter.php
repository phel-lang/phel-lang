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
        echo $this->getExceptionString($e, $codeSnippet);
    }

    public function getExceptionString(PhelCodeException $e, CodeSnippet $codeSnippet): string
    {
        $str = '';
        $eStartLocation = $e->getStartLocation() ?? $codeSnippet->getStartLocation();
        $eEndLocation = $e->getEndLocation() ?? $codeSnippet->getEndLocation();
        $firstLine = $eStartLocation->getLine();

        $str .= '<p>' . $e->getMessage() . '<br/>';
        $str .= 'in <em>' . $eStartLocation->getFile() . ':' . $firstLine . '</em></p>';

        $lines = explode("\n", $codeSnippet->getCode());
        $endLineLength = strlen((string)$codeSnippet->getEndLocation()->getLine());
        $padLength = $endLineLength - strlen((string)$codeSnippet->getStartLocation()->getLine());
        $str .= '<pre><code>';
        foreach ($lines as $index => $line) {
            $str .= str_pad((string)($firstLine + $index), $padLength, ' ', STR_PAD_LEFT);
            $str .= '| ' . htmlspecialchars($line) . "\n";

            $eStartLine = $eStartLocation->getLine();
            if ($eStartLine === $eEndLocation->getLine()
                && $eStartLine === $index + $codeSnippet->getStartLocation()->getLine()
            ) {
                $str .= str_repeat(' ', $endLineLength + 2 + $eStartLocation->getColumn());
                $str .= str_repeat('^', max(1, $eEndLocation->getColumn() - $eStartLocation->getColumn()));
                $str .= "\n";
            }
        }

        $str .= '</pre></code>';

        if ($e->getPrevious()) {
            $str .= '<p>Caused by:</p>';
            $str .= '<pre><code>';
            $str .= $e->getPrevious()->getTraceAsString();
            $str .= '</code></pre>';
        }

        return $str;
    }

    public function printStackTrace(Throwable $e): void
    {
        echo $this->getStackTraceString($e);
    }

    public function getStackTraceString(Throwable $e): string
    {
        $str = '';
        $type = get_class($e);
        $msg = $e->getMessage();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();

        $str .= "<div>$type: $msg in $errorFile:$errorLine</div>";

        $str .= '<ul>';
        foreach ($e->getTrace() as $i => $frame) {
            $class = $frame['class'] ?? null;
            $file = $frame['file'];
            $line = $frame['line'];

            if ($class) {
                $rf = new ReflectionClass($class);
                if ($rf->implementsInterface(FnInterface::class)) {
                    $fnName = $this->munge->decodeNs($rf->getConstant('BOUND_TO'));
                    $argString = $this->exceptionArgsPrinter->parseArgsAsString($frame['args'] ?? []);
                    $str .= "<li>#$i $file($line): ($fnName$argString)</li>";

                    continue;
                }
            }

            $class = $class ?? '';
            $type = $frame['type'] ?? '';
            $fn = $frame['function'];
            $argString = $this->exceptionArgsPrinter->buildPhpArgsString($frame['args']);
            $str .= "<li>#$i $file($line): $class$type$fn($argString)</li>";
        }

        $str .= '</ul>';

        return $str;
    }
}
