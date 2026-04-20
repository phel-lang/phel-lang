<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\Diagnostic;

interface AnalysisStageInterface
{
    /**
     * Run this stage against source + uri. Each stage can emit
     * diagnostics or feed state into following stages via $context.
     *
     * @param array<string, mixed> $context shared state between stages
     *
     * @return list<Diagnostic>
     */
    public function run(string $source, string $uri, array &$context): array;
}
