<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

use Phel\Shared\LintRuleCodes;

/**
 * What one {@see LintRuleCodes} rule reports, in the same four parts an
 * {@see \Phel\Shared\Exceptions\ErrorCodeExplanation} gives a `PHELxxx` code.
 *
 * @internal
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

    /**
     * @return array{code: string, title: string, summary: string, example: string, fix: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'summary' => $this->summary,
            'example' => $this->example,
            'fix' => $this->fix,
        ];
    }
}
