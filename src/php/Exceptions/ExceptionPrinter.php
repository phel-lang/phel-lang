<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\CodeSnippet;
use Throwable;

interface ExceptionPrinter
{
    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void;

    public function printStackTrace(Throwable $e): void;
}
