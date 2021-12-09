<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\RecurNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;

final class RecurEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof RecurNode);

        $tempSyms = [];
        foreach ($node->getExpressions() as $expr) {
            $tempSym = Symbol::gen();
            $tempSyms[] = $tempSym;

            $this->outputEmitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($expr);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        $params = $node->getFrame()->getParams();

        foreach ($tempSyms as $i => $tempSym) {
            $paramSym = $params[$i];
            $loc = $paramSym->getStartLocation();
            $normalizedParam = $node->getEnv()->getShadowed($paramSym) ?: $paramSym;

            $this->outputEmitter->emitPhpVariable($normalizedParam, $loc);
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitLine('continue;', $node->getStartSourceLocation());
    }
}
