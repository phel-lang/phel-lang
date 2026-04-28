<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

use Phel\Api\Transfer\Diagnostic;

interface LintRuleInterface
{
    /**
     * Stable identifier used for config lookups and output.
     */
    public function code(): string;

    /**
     * @return list<Diagnostic>
     */
    public function apply(FileAnalysis $analysis): array;
}
