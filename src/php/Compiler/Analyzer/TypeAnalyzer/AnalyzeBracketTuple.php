<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Analyzer\Ast\TupleNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\AbstractType;
use Phel\Lang\Tuple;

final class AnalyzeBracketTuple
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): TupleNode
    {
        $args = [];

        /** @var AbstractType|string|float|int|bool|null $arg */
        foreach ($tuple as $arg) {
            $envDisallowRecur = $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame();
            $args[] = $this->analyzer->analyze($arg, $envDisallowRecur);
        }

        return new TupleNode($env, $args, $tuple->getStartLocation());
    }
}
