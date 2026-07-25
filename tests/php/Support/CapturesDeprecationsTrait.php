<?php

declare(strict_types=1);

namespace PhelTest\Support;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * Captures the `E_USER_DEPRECATED` notices raised while deprecation warnings
 * are on.
 *
 * Detectors hold no injectable emitter: every notice goes through the one
 * `DeprecationWarnings` gate and out as a real PHP deprecation, so a test
 * observes them the same way a user does.
 *
 * Call `startCapturingDeprecations()` (enables the switch and installs the
 * handler) and `stopCapturingDeprecations()` (restores both). Calling stop
 * without a matching start is safe, so it belongs in `tearDown()`.
 */
trait CapturesDeprecationsTrait
{
    /** @var list<string> */
    private array $capturedDeprecations = [];

    private bool $deprecationHandlerInstalled = false;

    private function startCapturingDeprecations(): void
    {
        $this->capturedDeprecations = [];
        DeprecationWarnings::reset();
        DeprecationWarnings::enable();

        set_error_handler(
            function (int $errno, string $message): bool {
                $this->capturedDeprecations[] = $message;

                return true;
            },
            E_USER_DEPRECATED,
        );
        $this->deprecationHandlerInstalled = true;
    }

    private function stopCapturingDeprecations(): void
    {
        if ($this->deprecationHandlerInstalled) {
            restore_error_handler();
            $this->deprecationHandlerInstalled = false;
        }

        DeprecationWarnings::reset();
    }

    /**
     * @return list<string>
     */
    private function capturedDeprecations(): array
    {
        return $this->capturedDeprecations;
    }
}
