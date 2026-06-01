<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
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

        $env = $node->getEnv();
        if ($env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->emitTernaryCondition($node);
            return;
        }

        if ($this->tryEmitReturnTernary($node)) {
            return;
        }

        if ($this->tryEmitStatementWithoutNilElse($node)) {
            return;
        }

        $this->emitIfElseCondition($node);
    }

    /**
     * In RETURN context, if both branches emit as bare PHP expressions
     * we can collapse the whole `if (…) { return a; } else { return b; }`
     * to a single `return (cond ? a : b);` statement. Smaller bytecode
     * and one fewer basic block for the JIT.
     */
    private function tryEmitReturnTernary(IfNode $node): bool
    {
        if (!$node->getEnv()->isContext(NodeEnvironment::CONTEXT_RETURN)) {
            return false;
        }

        $then = $node->getThenExpr();
        $else = $node->getElseExpr();
        if (!self::isSimpleExpressionNode($then) || !self::isSimpleExpressionNode($else)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('return (', $loc);
        $this->emitTestExpr($node->getTestExpr());
        $this->outputEmitter->emitStr(' ? ', $loc);
        $this->emitNodeAsExpression($then);
        $this->outputEmitter->emitStr(' : ', $loc);
        $this->emitNodeAsExpression($else);
        $this->outputEmitter->emitLine(');', $loc);

        return true;
    }

    /**
     * In statement context, drop the `else { … }` branch when it is
     * just `nil` — that is the shape `(when …)` / `(when-not …)`
     * expand to, and emitting `} else { null; }` is dead bytecode.
     */
    private function tryEmitStatementWithoutNilElse(IfNode $node): bool
    {
        if (!$node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            return false;
        }

        $else = $node->getElseExpr();
        if (!$else instanceof LiteralNode || $else->getValue() !== null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('if (', $loc);
        $this->emitTestExpr($node->getTestExpr());
        $this->outputEmitter->emitLine(') {', $loc);
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getThenExpr());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('}', $loc);

        return true;
    }

    /**
     * Chain leaves and ternary branches are emitted directly into a
     * surrounding expression context, so each one must render as a
     * single PHP expression — `LetNode` / `IfNode` in `RETURN`
     * context expand to multi-statement bodies that the strip helper
     * cannot safely flatten.
     */
    private static function isSimpleExpressionNode(AbstractNode $node): bool
    {
        if ($node instanceof DoNode) {
            return $node->getStmts() === [] && self::isSimpleExpressionNode($node->getRet());
        }

        if ($node instanceof LocalVarNode
            || $node instanceof LiteralNode
            || $node instanceof GlobalVarNode
            || $node instanceof PhpVarNode
        ) {
            return true;
        }

        if ($node instanceof CallNode) {
            return array_all([$node->getFn(), ...$node->getArguments()], static fn(AbstractNode $child): bool => self::isSimpleExpressionNode($child));
        }

        return false;
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
     * Emit a node as a bare expression — the same node may sit deep inside
     * a chain whose original env is RETURN, but our chunk is consumed
     * as a bare expression in the surrounding `if (…)` test position,
     * so the context decoration would produce invalid PHP.
     */
    private function emitNodeAsExpression(AbstractNode $node): void
    {
        $this->outputEmitter->emitStr(
            $this->outputEmitter->captureNodeAsExpression($node),
            $node->getStartSourceLocation(),
        );
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
