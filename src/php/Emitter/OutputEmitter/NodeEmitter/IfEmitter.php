<?php

declare(strict_types=1);

namespace Phel\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\IfNode;
use Phel\Ast\Node;
use Phel\Emitter\OutputEmitter\NodeEmitter;
use Phel\NodeEnvironment;

final class IfEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof IfNode);

        if ($node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR) {
            $this->outputEmitter->emitStr('((\Phel\Lang\Truthy::isTruthy(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($node->getTestExpr());
            $this->outputEmitter->emitStr(')) ? ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($node->getThenExpr());
            $this->outputEmitter->emitStr(' : ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($node->getElseExpr());
            $this->outputEmitter->emitStr(')');
        } else {
            $this->outputEmitter->emitStr('if (\Phel\Lang\Truthy::isTruthy(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($node->getTestExpr());
            $this->outputEmitter->emitLine(')) {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitNode($node->getThenExpr());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitLine('} else {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitNode($node->getElseExpr());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
        }
    }
}
