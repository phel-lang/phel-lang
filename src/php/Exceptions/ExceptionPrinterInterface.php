<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\Compiler\CodeSnippet;
use Throwable;

interface ExceptionPrinterInterface
{
    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void;

    public function printStackTrace(Throwable $e): void;
}
