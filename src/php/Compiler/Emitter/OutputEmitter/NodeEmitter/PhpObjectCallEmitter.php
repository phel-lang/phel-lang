<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\MethodCallNode;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Ast\PhpClassNameNode;
use Phel\Compiler\Ast\PhpObjectCallNode;
use Phel\Compiler\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;
use RuntimeException;

final class PhpObjectCallEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
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
