<?php

declare(strict_types=1);

namespace Phel\Shared;

/**
 * What one {@see LintRuleCodes} rule reports, in the same four parts an
 * {@see Exceptions\ErrorCodeExplanation} gives a `PHELxxx` code.
 */
final readonly class LintRuleExplanation
{
    public function __construct(
        public string $code,
        public string $title,
        public string $summary,
        public string $example,
        public string $fix,
    ) {}
}
