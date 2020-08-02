<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\PhpClassNameNode;
use Phel\Ast\PhpNewNode;
use Phel\Emitter\NodeEmitter;
use Phel\Lang\Symbol;

final class PhpNewEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpNewNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $classExpr = $node->getClassExpr();

        if ($classExpr instanceof PhpClassNameNode) {
            $this->emitter->emitStr('(new ', $node->getStartSourceLocation());
            $this->emitter->emitNode($classExpr);
            $this->emitter->emitStr('(', $node->getStartSourceLocation());
        } else {
            $this->emitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

            $targetSym = Symbol::gen('target_');
            $this->emitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->emitter->emitNode($classExpr);
            $this->emitter->emitLine(';', $node->getStartSourceLocation());

            $this->emitter->emitStr('return new $' . $targetSym->getName() . '(', $node->getStartSourceLocation());
        }

        // Args
        $this->emitter->emitArgList($node->getArgs(), $node->getStartSourceLocation());

        if ($classExpr instanceof PhpClassNameNode) {
            $this->emitter->emitStr('))', $node->getStartSourceLocation());
        } else {
            $this->emitter->emitStr(');', $node->getStartSourceLocation());
            $this->emitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }

        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
