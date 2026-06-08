<?php

declare(strict_types=1);

namespace Phel\Shared\Eval;

/**
 * Pure value object describing the outcome of evaluating Phel code: a success
 * with a value, an incomplete (unfinished) form, or a failure carrying an
 * {@see EvalError}. The orchestration that produces it lives in
 * `Phel\Run\Application\StructuredEvaluator`; this type only models the result.
 */
final readonly class EvalResult
{
    private function __construct(
        public bool $success,
        public bool $incomplete,
        public mixed $value,
        public ?EvalError $error,
        public string $output,
    ) {}

    public static function success(mixed $value, string $output = ''): self
    {
        return new self(success: true, incomplete: false, value: $value, error: null, output: $output);
    }

    public static function incomplete(string $output = ''): self
    {
        return new self(success: false, incomplete: true, value: null, error: null, output: $output);
    }

    public static function failure(EvalError $error, string $output = ''): self
    {
        return new self(success: false, incomplete: false, value: null, error: $error, output: $output);
    }
}
