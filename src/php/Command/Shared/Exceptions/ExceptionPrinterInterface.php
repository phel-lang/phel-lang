<?php

declare(strict_types=1);

namespace Phel\Command\Shared\Exceptions;

use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Throwable;

interface ExceptionPrinterInterface
{
    public function printException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void;

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string;

    public function printStackTrace(Throwable $e): void;

    public function getStackTraceString(Throwable $e): string;
}
