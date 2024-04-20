<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

interface SpecialFormAnalyzerInterface
{
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode;
}
