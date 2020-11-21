<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\Node;
use Phel\Compiler\Ast\PhpClassNameNode;
use Phel\Compiler\Ast\PhpNewNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;
use Phel\Lang\Symbol;

final class PhpNewEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpNewNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $classExpr = $node->getClassExpr();

        if ($classExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr('(new ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($classExpr);
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

            $targetSym = Symbol::gen('target_');
            $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($classExpr);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

            $this->outputEmitter->emitStr('return new $' . $targetSym->getName() . '(', $node->getStartSourceLocation());
        }

        // Args
        $this->outputEmitter->emitArgList($node->getArgs(), $node->getStartSourceLocation());

        if ($classExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr('))', $node->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitStr(');', $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
