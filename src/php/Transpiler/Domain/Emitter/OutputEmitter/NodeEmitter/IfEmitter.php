<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Ast\IfNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class IfEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof IfNode);

        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->emitTernaryCondition($node);
        } else {
            $this->emitIfElseCondition($node);
        }
    }

    private function emitTernaryCondition(IfNode $node): void
    {
        $this->outputEmitter->emitStr('((\Phel\Lang\Truthy::isTruthy(', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getTestExpr());
        $this->outputEmitter->emitStr(')) ? ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getThenExpr());
        $this->outputEmitter->emitStr(' : ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getElseExpr());
        $this->outputEmitter->emitStr(')');
    }

    private function emitIfElseCondition(IfNode $node): void
    {
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
