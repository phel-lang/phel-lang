<?php

declare(strict_types=1);

namespace Phel\Exceptions\ExceptionPrinter;

use Phel\Printer\PrinterInterface;

trait ExceptionPrinterTrait
{
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
        if (null === $arg) {
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

    private function parseArgsAsString(PrinterInterface $printer, array $frameArgs): string
    {
        $argParts = array_map(
            /** @param mixed $arg */
            static fn ($arg) => $printer->print($arg),
            $frameArgs
        );

        $argString = implode(' ', $argParts);
        if (count($argParts) > 0) {
            $argString = ' ' . $argString;
        }

        return $argString;
    }
}
