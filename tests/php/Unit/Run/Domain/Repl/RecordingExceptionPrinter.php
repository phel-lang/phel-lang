<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

/**
 * Returns a canned user-facing trace and records the `$showInternalFrames`
 * argument each call received, which is how the tests see that `--stack-trace`
 * reaches the one shared filter.
 */
final class RecordingExceptionPrinter implements ExceptionPrinterInterface
{
    /** @var list<bool> */
    public array $showInternalFrames = [];

    public function __construct(
        private readonly string $trace,
    ) {}

    public function getStackTraceString(Throwable $e): string
    {
        return $this->trace;
    }

    public function printStackTrace(Throwable $e): void {}

    public function getUserFacingTraceString(Throwable $e, bool $showInternalFrames = false): string
    {
        $this->showInternalFrames[] = $showInternalFrames;

        return $this->trace;
    }

    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string
    {
        return '';
    }
}
