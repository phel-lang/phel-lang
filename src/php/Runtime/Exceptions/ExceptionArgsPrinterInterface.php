<?php

declare(strict_types=1);

namespace Phel\Runtime\Exceptions;

interface ExceptionArgsPrinterInterface
{
    public function buildPhpArgsString(array $args): string;

    public function parseArgsAsString(array $frameArgs): string;
}
