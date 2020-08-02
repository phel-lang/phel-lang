<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\MethodCallNode;
use Phel\Ast\Node;
use Phel\Ast\PhpClassNameNode;
use Phel\Ast\PhpObjectCallNode;
use Phel\Ast\PropertyOrConstantAccessNode;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;
use Phel\Lang\Symbol;
use RuntimeException;

final class PhpObjectCallEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof PhpObjectCallNode);

        $fnCode = $node->isStatic() ? '::' : '->';
        $targetExpr = $node->getTargetExpr();
        $callExpr = $node->getCallExpr();

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        if ($node->isStatic() && $targetExpr instanceof PhpClassNameNode) {
            $this->emitter->emitStr('(', $node->getStartSourceLocation());
            $this->emitter->emitNode($targetExpr);
            $this->emitter->emitStr($fnCode, $node->getStartSourceLocation());
        } else {
            $this->emitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

            $targetSym = Symbol::gen('target_');
            $this->emitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->emitter->emitNode($targetExpr);
            $this->emitter->emitLine(';', $node->getStartSourceLocation());

            $this->emitter->emitStr('return ', $node->getStartSourceLocation());
            $this->emitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            $this->emitter->emitStr($fnCode, $node->getStartSourceLocation());
        }

        // Method/Property and Arguments
        if ($callExpr instanceof MethodCallNode) {
            $this->emitter->emitStr($callExpr->getFn()->getName(), $callExpr->getFn()->getStartLocation());
            $this->emitter->emitStr('(', $node->getStartSourceLocation());
            $this->emitter->emitArgList($callExpr->getArgs(), $node->getStartSourceLocation());
            $this->emitter->emitStr(')', $node->getStartSourceLocation());
        } elseif ($callExpr instanceof PropertyOrConstantAccessNode) {
            $this->emitter->emitStr($callExpr->getName()->getName(), $callExpr->getName()->getStartLocation());
        } else {
            throw new RuntimeException('Not supported ' . get_class($callExpr));
        }

        // Close Expression
        if ($targetExpr instanceof PhpClassNameNode && $node->isStatic()) {
            $this->emitter->emitStr(')', $node->getStartSourceLocation());
        } else {
            $this->emitter->emitStr(';', $node->getStartSourceLocation());
            $this->emitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }

        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
