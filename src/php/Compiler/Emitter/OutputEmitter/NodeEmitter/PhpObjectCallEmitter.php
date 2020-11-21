<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\MethodCallNode;
use Phel\Ast\Node;
use Phel\Ast\PhpClassNameNode;
use Phel\Ast\PhpObjectCallNode;
use Phel\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;
use Phel\Lang\Symbol;
use RuntimeException;

final class PhpObjectCallEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpObjectCallNode);

        $fnCode = $node->isStatic() ? '::' : '->';
        $targetExpr = $node->getTargetExpr();
        $callExpr = $node->getCallExpr();

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        if ($node->isStatic() && $targetExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($targetExpr);
            $this->outputEmitter->emitStr($fnCode, $node->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

            $targetSym = Symbol::gen('target_');
            $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($targetExpr);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

            $this->outputEmitter->emitStr('return ', $node->getStartSourceLocation());
            $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr($fnCode, $node->getStartSourceLocation());
        }

        // Method/Property and Arguments
        if ($callExpr instanceof MethodCallNode) {
            $this->outputEmitter->emitStr($callExpr->getFn()->getName(), $callExpr->getFn()->getStartLocation());
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitArgList($callExpr->getArgs(), $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        } elseif ($callExpr instanceof PropertyOrConstantAccessNode) {
            $this->outputEmitter->emitStr($callExpr->getName()->getName(), $callExpr->getName()->getStartLocation());
        } else {
            throw new RuntimeException('Not supported ' . get_class($callExpr));
        }

        // Close Expression
        if ($targetExpr instanceof PhpClassNameNode && $node->isStatic()) {
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
