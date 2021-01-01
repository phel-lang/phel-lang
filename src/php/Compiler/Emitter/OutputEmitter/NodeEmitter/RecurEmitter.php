<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Ast\RecurNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;
use Phel\Lang\Symbol;

final class RecurEmitter implements NodeEmitter
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof RecurNode);

        $params = $node->getFrame()->getParams();
        $exprs = $node->getExpressions();
        $env = $node->getEnv();

        $tempSyms = [];
        foreach ($exprs as $i => $expr) {
            $tempSym = Symbol::gen();
            $tempSyms[] = $tempSym;

            $this->outputEmitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($expr);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        foreach ($tempSyms as $i => $tempSym) {
            $paramSym = $params[$i];
            $loc = $paramSym->getStartLocation();
            $shadowedSym = $env->getShadowed($paramSym);
            if ($shadowedSym) {
                $paramSym = $shadowedSym;
            }

            $this->outputEmitter->emitPhpVariable($paramSym, $loc);
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitLine('continue;', $node->getStartSourceLocation());
    }
}
