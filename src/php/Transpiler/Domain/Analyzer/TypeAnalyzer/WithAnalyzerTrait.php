<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Transpiler\Domain\Analyzer\AnalyzerInterface;

trait WithAnalyzerTrait
{
    public function __construct(private AnalyzerInterface $analyzer)
    {
    }
}
