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
     * Emit the `if` test expression: either as the lowered native
     * `(a) || (b) || ...` / `(a) && (b) && ...` chain when the test
     * is an expanded `(or …)` / `(and …)` form, or as the generic
     * `($__truthy = expr) !== null && $__truthy !== false` adapter.
     */
    private function emitTestExpr(AbstractNode $testExpr): void
    {
        if ($this->tryEmitShortCircuitTest($testExpr, AndOrShortCircuitLowerer::extractOrChain($testExpr), '||')) {
            return;
        }

        if ($this->tryEmitShortCircuitTest($testExpr, AndOrShortCircuitLowerer::extractAndChain($testExpr), '&&')) {
            return;
        }

        $isBool = BooleanExprDetector::isBool($testExpr);
        if ($isBool) {
            $this->outputEmitter->emitStr('(', $testExpr->getStartSourceLocation());
            $this->outputEmitter->emitNode($testExpr);
            $this->outputEmitter->emitStr(')', $testExpr->getStartSourceLocation());
            return;
        }

        $this->outputEmitter->emitStr('($__truthy = ', $testExpr->getStartSourceLocation());
        $this->outputEmitter->emitNode($testExpr);
        $this->outputEmitter->emitStr(') !== null && $__truthy !== false', $testExpr->getStartSourceLocation());
    }

    /**
     * @param list<AbstractNode>|null $chain
     */
    private function tryEmitShortCircuitTest(AbstractNode $testExpr, ?array $chain, string $op): bool
    {
        if ($chain === null) {
            return false;
        }

        $loc = $testExpr->getStartSourceLocation();
        foreach ($chain as $i => $arg) {
            if ($i > 0) {
                $this->outputEmitter->emitStr(' ' . $op . ' ', $loc);
            }

            if (BooleanExprDetector::isBool($arg)) {
                $this->outputEmitter->emitStr('(', $loc);
                $this->emitNodeAsExpression($arg);
                $this->outputEmitter->emitStr(')', $loc);
                continue;
            }

            $this->outputEmitter->emitStr('(($__truthy = ', $loc);
            $this->emitNodeAsExpression($arg);
            $this->outputEmitter->emitStr(') !== null && $__truthy !== false)', $loc);
        }

        return true;
    }

    /**
     * Emit a node and strip the analyser's `return ` prefix / trailing
     * `;` from the captured output — the same node may sit deep inside
     * a chain whose original env is RETURN, but our chunk is consumed
     * as a bare expression in the surrounding `if (…)` test position,
     * so the context decoration would produce invalid PHP.
     */
    private function emitNodeAsExpression(AbstractNode $node): void
    {
        ob_start();
        try {
            $this->outputEmitter->emitNode($node);
        } finally {
            $buf = (string) ob_get_clean();
        }

        $buf = preg_replace('/^return\s+/', '', $buf);
        if ($buf === null) {
            $buf = '';
        }

        $buf = rtrim($buf);
        if ($buf !== '' && str_ends_with($buf, ';')) {
            $buf = substr($buf, 0, -1);
        }

        $this->outputEmitter->emitStr($buf, $node->getStartSourceLocation());
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
        $this->outputEmitter->emitStr('(', $loc);
        $this->emitTestExpr($node->getTestExpr());
        $this->outputEmitter->emitStr(' ? ', $loc);
        $this->outputEmitter->emitNode($node->getThenExpr());
        $this->outputEmitter->emitStr(' : ', $loc);
        $this->outputEmitter->emitNode($node->getElseExpr());
        $this->outputEmitter->emitStr(')');
    }

    private function emitIfElseCondition(IfNode $node): void
    {
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('if (', $loc);
        $this->emitTestExpr($node->getTestExpr());
        $this->outputEmitter->emitLine(') {', $loc);

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
