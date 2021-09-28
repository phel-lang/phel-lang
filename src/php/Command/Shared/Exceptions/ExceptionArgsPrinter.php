<?php

declare(strict_types=1);

namespace Phel\Command\Shared\Exceptions;

use Phel\Printer\PrinterInterface;

final class ExceptionArgsPrinter implements ExceptionArgsPrinterInterface
{
    private PrinterInterface $printer;

    public function __construct(PrinterInterface $printer)
    {
        $this->printer = $printer;
    }

    public function parseArgsAsString(array $frameArgs): string
    {
        $argParts = array_map(
            /** @param mixed $arg */
            fn ($arg) => $this->printer->print($arg),
            $frameArgs
        );

        $argString = implode(' ', $argParts);
        if (count($argParts) > 0) {
            $argString = ' ' . $argString;
        }

        return $argString;
    }

    public function buildPhpArgsString(array $args): string
    {
        $result = array_map(
            fn ($arg) => $this->buildPhpArg($arg),
            $args
        );

        return implode(', ', $result);
    }

    /**
     * Converts a PHP type to a string.
     *
     * @param mixed $arg The argument
     */
    private function buildPhpArg($arg): string
    {
        if (null === $arg) {
            return 'NULL';
        }

        if (is_string($arg)) {
            $s = $arg;
            if (strlen($s) > 15) {
                $s = substr($s, 0, 15) . '...';
            }

            return "'{$s}'";
        }

        if (is_bool($arg)) {
            return ($arg) ? 'true' : 'false';
        }

        if (is_array($arg)) {
            return 'Array';
        }

        if (is_object($arg)) {
            return 'Object(' . get_class($arg) . ')';
        }

        if (is_resource($arg)) {
            /**
             * @psalm-suppress UndefinedFunction
             */
            return 'Resource id #' . \get_resource_id($arg);
        }

        return (string)$arg;
    }
}
