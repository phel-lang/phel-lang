<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\IfNode;
use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;

final class IfEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof IfNode);

        if ($node->getEnv()->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION) {
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
