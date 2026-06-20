<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions\Hint;

use Throwable;

/**
 * Maps a runtime/compile error to a short, actionable hint. Used both by the
 * REPL error formatter and by the CLI command error writer, so a failing
 * `phel run`/`phel test`/`phel eval` gets the same guidance as the REPL.
 */
interface ExceptionHintInterface
{
    public function appliesTo(Throwable $e): bool;

    public function hint(Throwable $e): string;
}
