<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;
use Phel\Ast\RecurNode;
use Phel\Emitter;
use Phel\Lang\Symbol;

final class RecurEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof RecurNode);

        $params = $node->getFrame()->getParams();
        $exprs = $node->getExpressions();
        $env = $node->getEnv();

        $tempSyms = [];
        foreach ($exprs as $i => $expr) {
            $tempSym = Symbol::gen();
            $tempSyms[] = $tempSym;

            $this->emitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->emitter->emit($expr);
            $this->emitter->emitLine(';', $node->getStartSourceLocation());
        }

        foreach ($tempSyms as $i => $tempSym) {
            $paramSym = $params[$i];
            $loc = $paramSym->getStartLocation();
            $shadowedSym = $env->getShadowed($paramSym);
            if ($shadowedSym) {
                $paramSym = $shadowedSym;
            }

            $this->emitter->emitPhpVariable($paramSym, $loc);
            $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->emitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->emitter->emitLine(';', $node->getStartSourceLocation());
        }

        $this->emitter->emitLine('continue;', $node->getStartSourceLocation());
    }
}
