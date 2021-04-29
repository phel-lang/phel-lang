<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

interface SpecialFormAnalyzerInterface
{
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode;
}
