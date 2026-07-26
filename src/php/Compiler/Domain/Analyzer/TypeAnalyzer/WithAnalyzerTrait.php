<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;

/**
 * @internal
 */
trait WithAnalyzerTrait
{
    public function __construct(
        private readonly AnalyzerInterface $analyzer,
    ) {}
}
