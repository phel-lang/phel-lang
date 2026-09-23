<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\TagResolver;

use function assert;
use function count;
use function in_array;
use function ltrim;

/**
 * @internal
 */
final class LetEmitter implements NodeEmitterInterface
{
    use LoweredMatchEmitterTrait;
    use WithOutputEmitterTrait;

    /** @var list<string> Tags that map straight to a PHP primitive type */
    private const array PRIMITIVE_TAGS = ['int', 'float', 'bool', 'string', 'array'];

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof LetNode);

        if ($this->tryEmitAsMatch($node)) {
            return;
        }

        if ($this->tryEmitAsShortCircuit($node)) {
            return;
        }

        if ($this->tryEmitInlineInExpression($node)) {
            return;
        }

        $isWrapFn = $node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION);
        if ($isWrapFn) {
            $this->outputEmitter->emitFnWrapPrefix(
                $node->getEnv(),
                $node->getStartSourceLocation(),
                new ByRefLocalCollector()->collect($node),
            );
        }

        foreach ($node->getBindings() as $bindingNode) {
            $docType = $this->doctagType($bindingNode->getSymbol());
            if ($docType !== null) {
                $this->outputEmitter->emitLine(
                    '/** @var ' . $docType . ' $' . $this->outputEmitter->mungeEncode($bindingNode->getShadow()->getName()) . ' */',
                    $bindingNode->getStartSourceLocation(),
                );
            }

            $this->outputEmitter->emitPhpVariable($bindingNode->getShadow(), $bindingNode->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($bindingNode->getInitExpr());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        if ($node->isLoop()) {
            $this->outputEmitter->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
        }

        $this->outputEmitter->emitNode($node->getBodyExpr());

        if ($node->isLoop()) {
            $this->outputEmitter->emitLine('break;', $node->getStartSourceLocation());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
        }

        if ($isWrapFn) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }

    /**
     * Lower a `case` / `cond`-shaped `LetNode` to a single PHP `match`
     * expression. See {@see IfChainMatchLowerer} for the detection
     * rules.
     *
     * Only fires when the let is in expression / return context — PHP
     * `match` always evaluates the dispatch, so dropping the result
     * in statement context is wasted work the generic if/else path
     * handles cheaper.
     */
    private function tryEmitAsMatch(LetNode $node): bool
    {
        $env = $node->getEnv();
        if ($env->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            return false;
        }

        $shape = IfChainMatchLowerer::analyse($node);
        if ($shape === null) {
            return false;
        }

        $this->emitLoweredMatch($shape, $env, $node->getStartSourceLocation());

        return true;
    }

    /**
     * Lower the expanded `(or …)` / `(and …)` macro shapes to a
     * nested PHP ternary that preserves the Phel value-semantics
     * (return the first truthy value / last falsy, not a bool).
     * Skips the IIFE wrap the generic let-in-expression path emits.
     *
     * Statement-context lets do not benefit (no value is consumed),
     * so we keep the generic emit there.
     */
    private function tryEmitAsShortCircuit(LetNode $node): bool
    {
        $env = $node->getEnv();
        if ($env->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            return false;
        }

        $chain = AndOrShortCircuitLowerer::extractOrChain($node);
        if ($chain !== null) {
            $this->emitValuePositionChain($node, $chain, true);
            return true;
        }

        $chain = AndOrShortCircuitLowerer::extractAndChain($node);
        if ($chain !== null) {
            $this->emitValuePositionChain($node, $chain, false);
            return true;
        }

        return false;
    }

    /**
     * Emit a flagged `let` in expression position as one PHP expression, each
     * binding an assignment chained ahead of the body, instead of an IIFE:
     *
     *     ((null !== ($a = <init>) || true) && … ? <body> : null)
     *
     * Every link is true, so each init runs once, in order, then the body.
     * Only a producer that knows the bindings may leak into the enclosing PHP
     * scope sets the flag ({@see LetNode::isInlineInExpression()}), and only a
     * body that is a single call is spliced: a call in return position emits
     * as `return <expr>;`, which strips to the bare expression.
     */
    private function tryEmitInlineInExpression(LetNode $node): bool
    {
        $env = $node->getEnv();
        if (!$node->isInlineInExpression() || $node->isLoop() || !$env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            return false;
        }

        $body = $node->getBodyExpr();
        $ret = $body instanceof DoNode && $body->getStmts() === [] ? $body->getRet() : $body;
        if (!$ret instanceof CallNode || $node->getBindings() === []) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitContextPrefix($env, $loc);
        $this->outputEmitter->emitStr('(', $loc);
        foreach ($node->getBindings() as $i => $bindingNode) {
            if ($i > 0) {
                $this->outputEmitter->emitStr(' && ', $loc);
            }

            $this->outputEmitter->emitStr('(null !== (', $loc);
            $this->outputEmitter->emitPhpVariable($bindingNode->getShadow(), $bindingNode->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $loc);
            $this->outputEmitter->emitNode($bindingNode->getInitExpr());
            $this->outputEmitter->emitStr(') || true)', $loc);
        }

        $this->outputEmitter->emitStr(' ? ', $loc);
        $this->outputEmitter->emitStr($this->outputEmitter->captureNodeAsExpression($ret), $ret->getStartSourceLocation());
        $this->outputEmitter->emitStr(' : null)', $loc);
        $this->outputEmitter->emitContextSuffix($env, $loc);

        return true;
    }

    /**
     * @param list<AbstractNode> $operands
     */
    private function emitValuePositionChain(LetNode $node, array $operands, bool $isOr): void
    {
        $env = $node->getEnv();
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitContextPrefix($env, $loc);

        $count = count($operands);
        for ($i = 0; $i < $count - 1; ++$i) {
            $this->outputEmitter->emitStr('((($__or = ', $loc);
            $this->emitOperandAsExpression($operands[$i]);
            $this->outputEmitter->emitStr(') !== null && $__or !== false) ? ', $loc);
            if ($isOr) {
                $this->outputEmitter->emitStr('$__or : ', $loc);
            }
        }

        $this->emitOperandAsExpression($operands[$count - 1]);

        $suffix = $isOr ? ')' : ' : $__or)';
        for ($i = 0; $i < $count - 1; ++$i) {
            $this->outputEmitter->emitStr($suffix, $loc);
        }

        $this->outputEmitter->emitContextSuffix($env, $loc);
    }

    /**
     * Emit a chain operand as a bare expression so the result fits inside
     * the ternary expression. Same trick `IfEmitter` uses for
     * test-position chains.
     */
    private function emitOperandAsExpression(AbstractNode $operand): void
    {
        $this->outputEmitter->emitStr(
            $this->outputEmitter->captureNodeAsExpression($operand),
            $operand->getStartSourceLocation(),
        );
    }

    /**
     * Map a binding symbol's `:tag` meta to a PHP doctag type string,
     * or `null` when the binding carries no tag. Primitive tags pass
     * through; anything else is treated as a class FQN and prefixed
     * with `\` so static analysers resolve it from the global namespace.
     */
    private function doctagType(Symbol $symbol): ?string
    {
        $meta = $symbol->getMeta();
        if (!$meta instanceof PersistentMapInterface) {
            return null;
        }

        $tag = TagResolver::normalizeScalar($meta->find(Keyword::create('tag')));
        if ($tag === null) {
            return null;
        }

        $normalised = ltrim($tag, '\\');
        if (in_array($normalised, self::PRIMITIVE_TAGS, true)) {
            return $normalised;
        }

        return '\\' . $normalised;
    }
}
