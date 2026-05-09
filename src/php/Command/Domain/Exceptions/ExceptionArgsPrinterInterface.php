<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

interface ExceptionArgsPrinterInterface
{
    /**
     * @param list<mixed> $args
     */
    public function buildPhpArgsString(array $args): string;

    /**
     * @param list<mixed> $frameArgs
     */
    public function parseArgsAsString(array $frameArgs): string;
}
