<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;

use function assert;

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
            $normalizedParam = $node->getEnv()->getShadowed($paramSym) instanceof Symbol
                ? $node->getEnv()->getShadowed($paramSym) ?? $paramSym
                : $paramSym;
            $this->outputEmitter->emitPhpVariable($normalizedParam, $loc);
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitLine('continue;', $node->getStartSourceLocation());
    }
}
