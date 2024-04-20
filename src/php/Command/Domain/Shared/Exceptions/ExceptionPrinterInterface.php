<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Shared\Exceptions;

use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Transpiler\Domain\Parser\ReadModel\CodeSnippet;
use Throwable;

interface ExceptionPrinterInterface
{
    public function printError(string $error): void;

    public function printException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void;

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string;

    public function printStackTrace(Throwable $e): void;

    public function getStackTraceString(Throwable $e): string;
}
