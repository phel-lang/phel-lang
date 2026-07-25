<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Shared\Api\Diagnostic;

interface SourceAnalyzerInterface
{
    /**
     * @return list<Diagnostic>
     */
    public function analyze(string $source, string $uri): array;
}
