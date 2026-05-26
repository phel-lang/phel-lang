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

        if ($this->tryEmitAsMatch($node)) {
            return;
        }

        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->emitTernaryCondition($node);
        } else {
            $this->emitIfElseCondition($node);
        }
    }

    /**
     * Lower a bare `cond`-shaped `if` chain (every test `(= x lit)`
     * against the same `LocalVarNode`, arm bodies + fallback are
     * primitive literals) to a single PHP `match` expression. See
     * {@see IfChainMatchLowerer::analyseIfChain()}.
     *
     * Statement-context chains keep the if/else path — `match`
     * would dispatch only to throw the result away.
     */
    private function tryEmitAsMatch(IfNode $node): bool
    {
        $env = $node->getEnv();
        if ($env->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            return false;
        }

        $shape = IfChainMatchLowerer::analyseIfChain($node);
        if ($shape === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitContextPrefix($env, $loc);
        $this->outputEmitter->emitStr('match (', $loc);
        $this->outputEmitter->emitNode($shape['init']);
        $this->outputEmitter->emitStr(') { ', $loc);

        foreach ($shape['arms'] as $arm) {
            $this->outputEmitter->emitLiteral($arm['key']);
            $this->outputEmitter->emitStr(' => ', $loc);
            $this->outputEmitter->emitLiteral($arm['expr']);
            $this->outputEmitter->emitStr(', ', $loc);
        }

        $this->outputEmitter->emitStr('default => ', $loc);
        $this->outputEmitter->emitLiteral($shape['fallback']);
        $this->outputEmitter->emitStr(' }', $loc);
        $this->outputEmitter->emitContextSuffix($env, $loc);

        return true;
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
