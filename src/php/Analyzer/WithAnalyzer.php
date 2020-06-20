<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Analyzer;

trait WithAnalyzer
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }
}
