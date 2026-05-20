<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\BooleanExprDetector;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

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
        $loc = $node->getStartSourceLocation();
        $isBool = BooleanExprDetector::isBool($node->getTestExpr());

        if ($isBool) {
            $this->outputEmitter->emitStr('((', $loc);
            $this->outputEmitter->emitNode($node->getTestExpr());
            $this->outputEmitter->emitStr(') ? ', $loc);
        } else {
            $this->outputEmitter->emitStr('(($__truthy = ', $loc);
            $this->outputEmitter->emitNode($node->getTestExpr());
            $this->outputEmitter->emitStr(') !== null && $__truthy !== false ? ', $loc);
        }

        $this->outputEmitter->emitNode($node->getThenExpr());
        $this->outputEmitter->emitStr(' : ', $loc);
        $this->outputEmitter->emitNode($node->getElseExpr());
        $this->outputEmitter->emitStr(')');
    }

    private function emitIfElseCondition(IfNode $node): void
    {
        $loc = $node->getStartSourceLocation();
        $isBool = BooleanExprDetector::isBool($node->getTestExpr());

        if ($isBool) {
            $this->outputEmitter->emitStr('if ((', $loc);
            $this->outputEmitter->emitNode($node->getTestExpr());
            $this->outputEmitter->emitLine(')) {', $loc);
        } else {
            $this->outputEmitter->emitStr('if (($__truthy = ', $loc);
            $this->outputEmitter->emitNode($node->getTestExpr());
            $this->outputEmitter->emitLine(') !== null && $__truthy !== false) {', $loc);
        }

        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getThenExpr());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('} else {', $loc);
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getElseExpr());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('}', $loc);
    }
}
