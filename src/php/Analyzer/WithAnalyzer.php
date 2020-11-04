<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\AnalyzerInterface;

trait WithAnalyzer
{
    private AnalyzerInterface $analyzer;

    public function __construct(AnalyzerInterface $analyzer)
    {
        $this->analyzer = $analyzer;
    }
}
