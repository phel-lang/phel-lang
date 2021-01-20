<?php

declare(strict_types=1);

namespace Phel\Exceptions\Printer;

interface ExceptionArgsPrinterInterface
{
    public function buildPhpArgsString(array $args): string;

    public function parseArgsAsString(array $frameArgs): string;
}
