<?php

declare(strict_types=1);

namespace Phel\Lint\Domain\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Built (never thrown) by `RulePipeline` when a lint rule throws while
 * analysing a file: it chains the original throwable and owns the wording of
 * the `phel/internal-error` diagnostic the pipeline reports instead.
 *
 * Skipping the rule silently would drop its findings for that file while the
 * run still printed "No lint issues found." and exited 0, and a clean report
 * is read as a guarantee the linter looked.
 *
 * @internal
 */
final class LintRuleException extends RuntimeException
{
    public static function ruleCrashed(string $ruleCode, Throwable $previous): self
    {
        return new self(
            sprintf(
                "Lint rule '%s' crashed and reported nothing for this file: %s: %s at %s:%d."
                . ' This is a linter bug, not a problem in the linted file;'
                . ' set the rule to :off in phel-lint.phel to skip it.',
                $ruleCode,
                $previous::class,
                $previous->getMessage(),
                $previous->getFile(),
                $previous->getLine(),
            ),
            0,
            $previous,
        );
    }
}
