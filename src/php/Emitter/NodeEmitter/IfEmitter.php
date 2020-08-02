<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\IfNode;
use Phel\Ast\Node;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class IfEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof IfNode);

        if ($node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR) {
            $this->emitter->emitStr('((\Phel\Lang\Truthy::isTruthy(', $node->getStartSourceLocation());
            $this->emitter->emitNode($node->getTestExpr());
            $this->emitter->emitStr(')) ? ', $node->getStartSourceLocation());
            $this->emitter->emitNode($node->getThenExpr());
            $this->emitter->emitStr(' : ', $node->getStartSourceLocation());
            $this->emitter->emitNode($node->getElseExpr());
            $this->emitter->emitStr(')');
        } else {
            $this->emitter->emitStr('if (\Phel\Lang\Truthy::isTruthy(', $node->getStartSourceLocation());
            $this->emitter->emitNode($node->getTestExpr());
            $this->emitter->emitLine(')) {', $node->getStartSourceLocation());
            $this->emitter->increaseIndentLevel();
            $this->emitter->emitNode($node->getThenExpr());
            $this->emitter->decreaseIndentLevel();
            $this->emitter->emitLine();
            $this->emitter->emitLine('} else {', $node->getStartSourceLocation());
            $this->emitter->increaseIndentLevel();
            $this->emitter->emitNode($node->getElseExpr());
            $this->emitter->decreaseIndentLevel();
            $this->emitter->emitLine();
            $this->emitter->emitLine('}', $node->getStartSourceLocation());
        }
    }
}
