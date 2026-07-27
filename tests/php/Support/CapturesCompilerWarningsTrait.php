<?php

declare(strict_types=1);

namespace PhelTest\Support;

use Phel\Compiler\Domain\Diagnostic\CompilerWarnings;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_WARNING;

/**
 * Captures the `E_USER_WARNING` notices the compiler raises.
 *
 * Sibling of {@see CapturesDeprecationsTrait}, minus the enable step: the
 * warning channel has no switch, so a test only has to install the handler and
 * drop the dedup table.
 *
 * Call `startCapturingCompilerWarnings()` and
 * `stopCapturingCompilerWarnings()`. Calling stop without a matching start is
 * safe, so it belongs in `tearDown()`.
 */
trait CapturesCompilerWarningsTrait
{
    /** @var list<string> */
    private array $capturedCompilerWarnings = [];

    private bool $compilerWarningHandlerInstalled = false;

    private function startCapturingCompilerWarnings(): void
    {
        $this->capturedCompilerWarnings = [];
        CompilerWarnings::reset();

        set_error_handler(
            function (int $errno, string $message): bool {
                $this->capturedCompilerWarnings[] = $message;

                return true;
            },
            E_USER_WARNING,
        );
        $this->compilerWarningHandlerInstalled = true;
    }

    private function stopCapturingCompilerWarnings(): void
    {
        if ($this->compilerWarningHandlerInstalled) {
            restore_error_handler();
            $this->compilerWarningHandlerInstalled = false;
        }

        CompilerWarnings::reset();
    }

    /**
     * @return list<string>
     */
    private function capturedCompilerWarnings(): array
    {
        return $this->capturedCompilerWarnings;
    }
}
