<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function assert;
use function count;
use function in_array;
use function is_string;
use function ltrim;

final class LetEmitter implements NodeEmitterInterface
{
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

        $tag = $meta->find(Keyword::create('tag'));
        if ($tag instanceof Symbol) {
            $tag = $tag->getName();
        }

        if (!is_string($tag) || $tag === '') {
            return null;
        }

        $normalised = ltrim($tag, '\\');
        if (in_array($normalised, self::PRIMITIVE_TAGS, true)) {
            return $normalised;
        }

        return '\\' . $normalised;
    }
}
