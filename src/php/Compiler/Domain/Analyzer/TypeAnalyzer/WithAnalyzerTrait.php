<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;

trait WithAnalyzerTrait
{
    public function __construct(private AnalyzerInterface $analyzer)
    {
    }
}
