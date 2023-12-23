<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Shared\Exceptions;

use Phel\Printer\PrinterInterface;

use function count;
use function is_array;
use function is_bool;
use function is_object;
use function is_resource;
use function is_string;
use function strlen;

final readonly class ExceptionArgsPrinter implements ExceptionArgsPrinterInterface
{
    public function __construct(
        private PrinterInterface $printer,
    ) {
    }

    public function parseArgsAsString(array $frameArgs): string
    {
        $argParts = array_map(
            fn (mixed $arg) => $this->printer->print($arg),
            $frameArgs,
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
            $args,
        );

        return implode(', ', $result);
    }

    /**
     * Converts a PHP type to a string.
     */
    private function buildPhpArg(mixed $arg): string
    {
        if ($arg === null) {
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
            return 'Object(' . $arg::class . ')';
        }

        if (is_resource($arg)) {
            /**
             * @psalm-suppress UndefinedFunction
             */
            return 'Resource id #' . get_resource_id($arg);
        }

        return (string)$arg;
    }
}
