<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

use Phel\Shared\Printer\PrinterInterface;

use function is_array;
use function is_bool;
use function is_object;
use function is_resource;
use function is_string;
use function sprintf;
use function strlen;

final readonly class ExceptionArgsPrinter implements ExceptionArgsPrinterInterface
{
    private const int MAX_ARG_LENGTH = 200;

    public function __construct(
        private PrinterInterface $printer,
    ) {}

    /**
     * @param list<mixed> $frameArgs
     */
    public function parseArgsAsString(array $frameArgs): string
    {
        $argParts = array_map(
            fn($arg): string => $this->truncate($this->printer->print($arg)),
            $frameArgs,
        );

        $argString = implode(' ', $argParts);
        if ($argParts !== []) {
            return ' ' . $argString;
        }

        return $argString;
    }

    /**
     * @param list<mixed> $args
     */
    public function buildPhpArgsString(array $args): string
    {
        $result = array_map(
            $this->buildPhpArg(...),
            $args,
        );

        return implode(', ', $result);
    }

    private function truncate(string $s): string
    {
        if (strlen($s) <= self::MAX_ARG_LENGTH) {
            return $s;
        }

        return substr($s, 0, self::MAX_ARG_LENGTH) . '...';
    }

    /**
     * Formats a single PHP value for display in a stack trace frame.
     *
     * Strings are quoted and truncated to 15 characters to keep trace lines
     * readable; resources are rendered as their resource id, objects as their
     * class name. This differs from {@see parseArgsAsString()}, which formats
     * Phel function arguments via the {@see PrinterInterface}.
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

            return sprintf("'%s'", $s);
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

        return (string) $arg;
    }
}
