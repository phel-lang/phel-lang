<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\SourceLocation;

/**
 * Emits the PHP `match` expression for a lowered `cond` / `case` chain
 * shape produced by {@see IfChainMatchLowerer}. Composing emitters must
 * also use {@see WithOutputEmitterTrait}.
 */
trait LoweredMatchEmitterTrait
{
    /**
     * @param array{init: AbstractNode, arms: list<array{key: mixed, expr: mixed}>, fallback: mixed} $shape
     */
    private function emitLoweredMatch(array $shape, NodeEnvironmentInterface $env, ?SourceLocation $loc): void
    {
        $this->outputEmitter->emitContextPrefix($env, $loc);
        $this->outputEmitter->emitStr('match (', $loc);
        $this->outputEmitter->emitNode($shape['init']);
        $this->outputEmitter->emitStr(') { ', $loc);

        foreach ($shape['arms'] as $arm) {
            $this->outputEmitter->emitLiteral($arm['key']);
            $this->outputEmitter->emitStr(' => ', $loc);
            $this->outputEmitter->emitLiteral($arm['expr']);
            $this->outputEmitter->emitStr(', ', $loc);
        }

        $this->outputEmitter->emitStr('default => ', $loc);
        $this->outputEmitter->emitLiteral($shape['fallback']);
        $this->outputEmitter->emitStr(' }', $loc);
        $this->outputEmitter->emitContextSuffix($env, $loc);
    }
}
