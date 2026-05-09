<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

interface SpecialFormAnalyzerInterface
{
    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode;
}
