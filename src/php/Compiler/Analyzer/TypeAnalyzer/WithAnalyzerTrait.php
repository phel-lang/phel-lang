<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Analyzer\AnalyzerInterface;

trait WithAnalyzerTrait
{
    private AnalyzerInterface $analyzer;

    public function __construct(AnalyzerInterface $analyzer)
    {
        $this->analyzer = $analyzer;
    }
}
