<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\Compiler\Exceptions\PhelCodeException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Throwable;

interface ExceptionPrinterInterface
{
    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void;

    public function getExceptionString(PhelCodeException $e, CodeSnippet $codeSnippet): string;

    public function printStackTrace(Throwable $e): void;

    public function getStackTraceString(Throwable $e): string;
}
