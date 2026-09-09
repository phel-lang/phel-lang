<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

use function str_contains;

/**
 * Code typed at the REPL prompt or handed to `phel eval` runs through PHP's
 * `eval()`, so its frames carry the evaluator's own path and a synthetic
 * `eval()'d code` suffix instead of a file the user could open. Both the `at`
 * line and the trace name it after the prompt it came from.
 *
 * @internal
 */
final class EvaluatedCodeLocation
{
    public const string LABEL = 'repl';

    private const string MARKER = "eval()'d code";

    public static function matches(string $filename): bool
    {
        return str_contains($filename, self::MARKER);
    }
}
