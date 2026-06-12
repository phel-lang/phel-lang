<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

interface ExceptionPrinterInterface
{
    public function printError(string $error): void;

    public function printException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void;

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string;

    public function printStackTrace(Throwable $e): void;

    public function getStackTraceString(Throwable $e): string;

    /**
     * Trace limited to frames originating in Phel code, mapped back to their
     * `.phel` source locations; PHP-native frames are collapsed.
     */
    public function getUserFacingTraceString(Throwable $e): string;
}
