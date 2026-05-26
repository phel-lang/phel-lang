<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitter\GlobalCallTarget;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpStringEscape;
use Phel\Lang\Symbol;

use function array_slice;
use function assert;
use function count;

final class CallEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof CallNode);

        $fnNode = $node->getFn();
        $isYield = $fnNode instanceof PhpVarNode && $fnNode->getName() === 'yield';

        if (!$isYield) {
            $this->emitContextPrefix($node);
        }

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            if ($fnNode->getName() === 'instanceof' && count($node->getArguments()) === 2) {
                $this->emitInstanceof($node);
            } else {
                $this->emitPhpVarNodeInfix($node, $fnNode);
            }
        } else {
            $this->emitPhpVarNode($node, $fnNode);
        }

        $this->emitContextSuffix($node);
    }

    private function emitContextPrefix(CallNode $node): void
    {
        $this->outputEmitter->emitContextPrefix(
            $node->getEnv(),
            $node->getStartSourceLocation(),
        );
    }

    private function emitContextSuffix(CallNode $node): void
    {
        $this->outputEmitter->emitContextSuffix(
            $node->getEnv(),
            $node->getStartSourceLocation(),
        );
    }

    private function emitPhpVarNodeInfix(CallNode $node, PhpVarNode $fnNode): void
    {
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $arguments = $node->getArguments();
        $argumentCount = count($arguments);
        foreach ($arguments as $i => $argument) {
            $this->emitInfixArgument($fnNode, $argument, $i);

            if ($i < $argumentCount - 1) {
                $this->outputEmitter->emitStr(' ' . $fnNode->getName() . ' ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function emitInstanceof(CallNode $node): void
    {
        $arguments = $node->getArguments();
        assert(count($arguments) === 2);
        [$value, $class] = $arguments;

        if ($class instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($value);
            $this->outputEmitter->emitStr(' instanceof ', $node->getStartSourceLocation());
            $this->outputEmitter->emitStr($class->getAbsolutePhpName(), $class->getName()->getStartLocation());
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
            return;
        }

        // Both args being local variables means re-emitting them is a
        // free, side-effect-free PHP variable reference, so we skip the
        // two-temp IIFE the generic path falls back to.
        if ($value instanceof LocalVarNode && $class instanceof LocalVarNode) {
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($value);
            $this->outputEmitter->emitStr(' instanceof ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($class);
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
            return;
        }

        $valueSym = Symbol::gen('instanceof_value_');
        $classSym = Symbol::gen('instanceof_class_');

        $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitPhpVariable($valueSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($value);
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitPhpVariable($classSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($class);
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitStr('return ', $node->getStartSourceLocation());
        $this->outputEmitter->emitPhpVariable($valueSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' instanceof ', $node->getStartSourceLocation());
        $this->outputEmitter->emitPhpVariable($classSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
    }

    private function emitInfixArgument(PhpVarNode $fnNode, AbstractNode $argument, int $index): void
    {
        if ($fnNode->getName() === 'instanceof' && $index === 1 && $argument instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr($argument->getAbsolutePhpName(), $argument->getName()->getStartLocation());
            return;
        }

        $this->outputEmitter->emitNode($argument);
    }

    private function emitPhpVarNode(CallNode $node, AbstractNode $fnNode): void
    {
        if ($fnNode instanceof PhpVarNode) {
            if ($fnNode->getName() === 'yield') {
                $this->emitYieldArguments($node, $fnNode);
                return;
            }

            $this->emitPhpFunctionName($fnNode);
            $this->emitFunctionArguments($node);
            return;
        }

        if ($this->tryEmitKeywordFind($node)) {
            return;
        }

        if ($this->tryEmitStrConcat($node)) {
            return;
        }

        if ($this->tryEmitTypedGetAccess($node)) {
            return;
        }

        if ($this->tryEmitTypedPhpArrayGet($node)) {
            return;
        }

        if ($this->tryEmitTypedPhpArrayCount($node)) {
            return;
        }

        if ($this->tryEmitNilCheck($node)) {
            return;
        }

        if ($this->tryEmitSomeCheck($node)) {
            return;
        }

        if ($this->tryEmitTrueCheck($node)) {
            return;
        }

        if ($this->tryEmitFalseCheck($node)) {
            return;
        }

        if ($this->tryEmitTruthyCheck($node)) {
            return;
        }

        if ($this->tryEmitNumericPredicate($node)) {
            return;
        }

        if ($this->tryEmitTypePredicate($node)) {
            return;
        }

        if ($this->tryEmitNamedAccessor($node)) {
            return;
        }

        if ($this->tryEmitEmptyCheck($node)) {
            return;
        }

        if ($this->tryEmitContainsCheck($node)) {
            return;
        }

        if ($this->tryEmitTypedVectorAccessor($node)) {
            return;
        }

        if ($this->tryEmitTypedSeqAccessor($node)) {
            return;
        }

        if ($this->tryEmitAssocConjChain($node)) {
            return;
        }

        if ($this->tryEmitTypedAssocConjDissoc($node)) {
            return;
        }

        if ($this->tryEmitNotEqPeephole($node)) {
            return;
        }

        if ($this->tryEmitTypedVariadicChain($node)) {
            return;
        }

        if ($this->tryEmitTypedBinaryOp($node)) {
            return;
        }

        $useCallMethod = !$this->isSelfCall($node)
            && (GlobalCallTarget::isGlobalFnCall($node) || CallSpecialization::isTypedAFnLocal($node));

        $this->emitDynamicFunctionName($node);

        if ($useCallMethod) {
            $this->emitCallMethodArguments($node);
        } else {
            $this->emitFunctionArguments($node);
        }
    }

    /**
     * Specialise `(str ...)` to PHP `.` concatenation when every arg
     * compiles to a string-typed expression. The runtime `phel.core/str`
     * does a per-arg `val-to-str` dispatch plus a `StringBuilder`-style
     * accumulator pass; when every arg is already a string the result is
     * the same plain `.` chain, so we emit it directly and skip both the
     * registry lookup and the runtime walk.
     *
     * Eligibility lives on {@see CallSpecialization::isStrConcat()} so the
     * cache scanner can skip reserving a `static $__phel_call_N` slot for
     * the call we are about to specialise.
     */
    private function tryEmitStrConcat(CallNode $node): bool
    {
        if (!CallSpecialization::isStrConcat($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        foreach ($node->getArguments() as $i => $arg) {
            if ($i > 0) {
                $this->outputEmitter->emitStr(' . ', $loc);
            }

            $this->outputEmitter->emitNode($arg);
        }

        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Specialise `(:k m)` to `$m->find(\Phel::keyword("k"))` when the
     * analyser has proved `m` to be a `PersistentMapInterface`. The
     * runtime `Keyword::__invoke` dispatches on the target's runtime
     * type to pick between `ArrayAccess`, `ContainsInterface`, and the
     * `nil` fallback; a typed map collapses that dispatch to the single
     * `find` call the map already exposes, returning `null` on miss to
     * match the 1-arg keyword-accessor contract.
     */
    private function tryEmitKeywordFind(CallNode $node): bool
    {
        if (!CallSpecialization::isKeywordFind($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr('->find(', $loc);
        $this->outputEmitter->emitNode($node->getFn());
        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }

    /**
     * Specialise two-arg `phel.core` arithmetic / comparison wrappers
     * to the native PHP binary op when both args are statically proven
     * primitive. The runtime defns route through `NumericOperations`
     * to handle `BigInt` / `Ratio` polymorphism; for primitive-typed
     * call sites that dispatch is wasted work and collapses to a
     * single PHP operator.
     *
     * Eligibility lives on {@see CallSpecialization::typedBinaryOpName()}.
     */
    private function tryEmitTypedBinaryOp(CallNode $node): bool
    {
        $op = CallSpecialization::typedBinaryOpName($node);
        if ($op === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr(' ' . $op . ' ', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Variadic (N>=3) numeric / ordering ops over tagged numeric locals.
     * `arith` ops chain as `($a + $b + $c)` (PHP is left-associative just
     * like Phel's variadic `+`/`*`); `compare` ops expand to a pairwise
     * `&&` chain `(($a < $b) && ($b < $c))` because PHP `<` does not
     * thread its result through the next comparison the way Phel does.
     *
     * Eligibility lives on {@see CallSpecialization::typedVariadicChain()}.
     */
    private function tryEmitTypedVariadicChain(CallNode $node): bool
    {
        $spec = CallSpecialization::typedVariadicChain($node);
        if ($spec === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $op = $spec['op'];
        $this->outputEmitter->emitStr('(', $loc);

        if ($spec['kind'] === 'arith') {
            $this->outputEmitter->emitNode($args[0]);
            for ($i = 1, $n = count($args); $i < $n; ++$i) {
                $this->outputEmitter->emitStr(' ' . $op . ' ', $loc);
                $this->outputEmitter->emitNode($args[$i]);
            }
        } else {
            for ($i = 0, $n = count($args) - 1; $i < $n; ++$i) {
                if ($i > 0) {
                    $this->outputEmitter->emitStr(' && ', $loc);
                }

                $this->outputEmitter->emitStr('(', $loc);
                $this->outputEmitter->emitNode($args[$i]);
                $this->outputEmitter->emitStr(' ' . $op . ' ', $loc);
                $this->outputEmitter->emitNode($args[$i + 1]);
                $this->outputEmitter->emitStr(')', $loc);
            }
        }

        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Specialise `(get coll k)` to a direct method call when the target
     * carries a `PersistentVectorInterface` or `PersistentMapInterface`
     * tag. Skips the cond chain in `phel.core/get`'s body (nil / set /
     * seq / php-aget fallback) for the hot indexed-access shape.
     *
     * Two-arg form only: the three-arg `(get coll k default)` shape
     * needs an explicit `contains?` probe to honour the default, so
     * the cond chain is still the right path.
     */
    private function tryEmitTypedGetAccess(CallNode $node): bool
    {
        $method = CallSpecialization::typedGetAccessMethod($node);
        if ($method === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $method . '(', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }

    /**
     * Specialise `(get arr k)` / `(get arr k default)` on a target
     * tagged `array` to a native PHP subscript with the null-coalescing
     * fallback. Matches the runtime `get` semantics for PHP arrays —
     * `(php/aget ds k)` then "if nil return default" — because PHP's
     * `??` treats both absent keys and explicit nulls as triggering
     * the fallback.
     */
    /**
     * Specialise `(count arr)` on a target tagged `array` to a native
     * `count(\$arr)` call. The runtime body would walk a cond chain
     * over the standard collection shapes before reaching the same
     * `php/count` branch.
     */
    private function tryEmitTypedPhpArrayCount(CallNode $node): bool
    {
        if (!CallSpecialization::isTypedPhpArrayCount($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('count(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * `(nil? x)` — emit `($x === null)` directly, bypassing the
     * registry lookup and `id` adapter.
     */
    private function tryEmitNilCheck(CallNode $node): bool
    {
        if (!CallSpecialization::isNilCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(' === null)', $loc);
        return true;
    }

    /**
     * `(some? x)` 1-arg — emit `($x !== null)` directly. The 2-arg
     * overload `(some? pred coll)` keeps the runtime dispatch.
     */
    private function tryEmitSomeCheck(CallNode $node): bool
    {
        if (!CallSpecialization::isSomeCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(' !== null)', $loc);
        return true;
    }

    private function tryEmitTrueCheck(CallNode $node): bool
    {
        if (!CallSpecialization::isTrueCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(' === true)', $loc);
        return true;
    }

    private function tryEmitFalseCheck(CallNode $node): bool
    {
        if (!CallSpecialization::isFalseCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(' === false)', $loc);
        return true;
    }

    /**
     * `(truthy? x)` — Phel-truthy probe inlined. Uses a fresh `$__truthy`
     * binding so the result is a bool the caller can splice into any
     * expression position.
     */
    private function tryEmitTruthyCheck(CallNode $node): bool
    {
        if (!CallSpecialization::isTruthyCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(($__truthy = ', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(') !== null && $__truthy !== false)', $loc);
        return true;
    }

    /**
     * `(contains? coll k)` on a tagged target — emit the direct
     * method or `array_key_exists` form.
     */
    private function tryEmitContainsCheck(CallNode $node): bool
    {
        $kind = CallSpecialization::containsCheckKind($node);
        if ($kind === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();

        if ($kind === 'array') {
            $this->outputEmitter->emitStr('array_key_exists(', $loc);
            $this->outputEmitter->emitNode($args[1]);
            $this->outputEmitter->emitStr(', ', $loc);
            $this->outputEmitter->emitNode($args[0]);
            $this->outputEmitter->emitStr(')', $loc);
            return true;
        }

        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->contains(', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }

    /**
     * `(empty? x)` on a tagged local — emit the native check
     * specific to the tag, skipping the runtime cond chain.
     */
    private function tryEmitEmptyCheck(CallNode $node): bool
    {
        $fragment = CallSpecialization::emptyCheckFragment($node);
        if ($fragment === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $parts = explode('%s', $fragment, 2);
        assert(count($parts) === 2);

        $this->outputEmitter->emitStr($parts[0], $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr($parts[1], $loc);
        return true;
    }

    /**
     * `(name x)` / `(namespace x)` on a target tagged
     * `\Phel\Lang\Keyword` or `\Phel\Lang\Symbol` — emit the direct
     * method call, skipping the runtime cond chain.
     */
    private function tryEmitNamedAccessor(CallNode $node): bool
    {
        $method = CallSpecialization::namedAccessorMethod($node);
        if ($method === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr('->' . $method . '())', $loc);
        return true;
    }

    /**
     * Splice the argument into the native predicate fragment from
     * `CallSpecialization::typePredicateFragment`. Used for `int?` /
     * `float?` / `string?` / `keyword?` / `symbol?` / `ratio?`.
     */
    private function tryEmitTypePredicate(CallNode $node): bool
    {
        $fragment = CallSpecialization::typePredicateFragment($node);
        if ($fragment === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $parts = explode('%s', $fragment, 2);
        assert(count($parts) === 2);

        $this->outputEmitter->emitStr($parts[0], $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr($parts[1], $loc);
        return true;
    }

    /**
     * `(zero? x)` / `(pos? x)` / `(neg? x)` on an `int` / `float`
     * tagged local — emit the native comparison directly.
     */
    private function tryEmitNumericPredicate(CallNode $node): bool
    {
        $name = CallSpecialization::isNumericPredicate($node);
        if ($name === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);

        $op = match ($name) {
            'zero?' => ' === 0',
            'pos?' => ' > 0',
            'neg?' => ' < 0',
            default => ' === 0',
        };

        $this->outputEmitter->emitStr($op . ')', $loc);
        return true;
    }

    private function tryEmitTypedPhpArrayGet(CallNode $node): bool
    {
        if (!CallSpecialization::isTypedPhpArrayGet($node)) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('[', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr('] ?? ', $loc);
        if (count($args) === 3) {
            $this->outputEmitter->emitNode($args[2]);
        } else {
            $this->outputEmitter->emitStr('null', $loc);
        }

        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * `(not (= a b))` peephole over the typed-`=` specialiser:
     * emits `($a !== $b)` directly, skipping both `phel.core/not`
     * and the explicit `!(($a === $b))` wrapper.
     *
     * Eligibility lives on {@see CallSpecialization::notEqPeepholeInner()}.
     */
    private function tryEmitNotEqPeephole(CallNode $node): bool
    {
        $inner = CallSpecialization::notEqPeepholeInner($node);
        if (!$inner instanceof CallNode) {
            return false;
        }

        $args = $inner->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr(' !== ', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Specialise a chain of `(assoc m k v)` calls (or `(conj v x)`
     * calls) on a typed persistent target — after thread-macro
     * expansion these are nested `CallNode`s of the same op rooted at
     * a `LocalVarNode`. The runtime path goes through one persistent
     * `put` / `append` per chain step, each allocating a new persistent
     * map / vector. The transient path opens one transient at the leaf
     * target, mutates it once per chain step, and snapshots back to a
     * persistent at the end — N-1 persistent intermediates collapse to
     * one.
     */
    private function tryEmitAssocConjChain(CallNode $node): bool
    {
        $chain = CallSpecialization::assocConjChain($node);
        if ($chain === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('((', $loc);
        $this->outputEmitter->emitNode($chain['target']);
        $this->outputEmitter->emitStr(')->asTransient()', $loc);

        foreach ($chain['groups'] as $group) {
            $this->outputEmitter->emitStr('->' . $chain['method'] . '(', $loc);
            foreach ($group as $i => $arg) {
                if ($i > 0) {
                    $this->outputEmitter->emitStr(', ', $loc);
                }

                $this->outputEmitter->emitNode($arg);
            }

            $this->outputEmitter->emitStr(')', $loc);
        }

        $this->outputEmitter->emitStr('->persistent())', $loc);
        return true;
    }

    /**
     * Specialise `(assoc m k v)` / `(assoc v i x)` / `(conj v x)` /
     * `(dissoc m k)` to a direct persistent-collection method when the
     * target tag is known. Skips variadic forms.
     */
    private function tryEmitTypedAssocConjDissoc(CallNode $node): bool
    {
        $method = CallSpecialization::typedAssocConjDissocMethod($node);
        if ($method === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $method . '(', $loc);

        $rest = array_slice($args, 1);
        $count = count($rest);
        foreach ($rest as $i => $arg) {
            $this->outputEmitter->emitNode($arg);
            if ($i < $count - 1) {
                $this->outputEmitter->emitStr(', ', $loc);
            }
        }

        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }

    /**
     * Specialise `(first s)` / `(rest s)` to a direct method call on
     * the tagged seq target. The runtime `phel.core/first` and
     * `phel.core/rest` bodies walk cond chains over nil / string /
     * php-array / set / map / seq; for a known seq tag every branch
     * collapses to `$s->first()` / `$s->rest()`.
     */
    private function tryEmitTypedSeqAccessor(CallNode $node): bool
    {
        $method = CallSpecialization::typedSeqMethodName($node);
        if ($method === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $method . '())', $loc);
        return true;
    }

    /**
     * Specialise `(nth v i)` / `(count v)` to a direct method call on
     * the tagged `PersistentVectorInterface` target. The runtime
     * `phel.core/nth` body walks a `cond` over set / seq / vector /
     * map / php-array; for a typed vector every branch collapses to
     * a single method call.
     */
    private function tryEmitTypedVectorAccessor(CallNode $node): bool
    {
        $spec = CallSpecialization::typedVectorMethodCall($node);
        if ($spec === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $spec['method'] . '(', $loc);

        $argCount = count($spec['args']);
        foreach ($spec['args'] as $i => $argIndex) {
            $this->outputEmitter->emitNode($args[$argIndex]);
            if ($i < $argCount - 1) {
                $this->outputEmitter->emitStr(', ', $loc);
            }
        }

        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }

    private function emitPhpFunctionName(PhpVarNode $fnNode): void
    {
        $name = $fnNode->getAbsoluteName();

        if ($name === 'echo') {
            $name = 'print';
        }

        $this->outputEmitter->emitStr($name, $fnNode->getStartSourceLocation());
    }

    private function emitYieldArguments(CallNode $node, PhpVarNode $fnNode): void
    {
        $this->outputEmitter->emitStr('yield', $fnNode->getStartSourceLocation());

        $args = $node->getArguments();
        $argsCount = count($args);
        if ($argsCount > 0) {
            $this->outputEmitter->emitStr(' ', $fnNode->getStartSourceLocation());
            $this->outputEmitter->emitNode($args[0]);

            if ($argsCount === 2) {
                $this->outputEmitter->emitStr(' => ', $fnNode->getStartSourceLocation());
                $this->outputEmitter->emitNode($args[1]);
            }
        }
    }

    private function emitDynamicFunctionName(CallNode $node): void
    {
        if ($this->isSelfCall($node)) {
            $this->outputEmitter->emitStr('$this', $node->getStartSourceLocation());
            return;
        }

        $fn = $node->getFn();
        $slot = $this->outputEmitter->callSlotFor($node);

        if ($slot !== null && $fn instanceof GlobalVarNode) {
            $this->emitCachedGlobalFn($node, $fn, $slot);
            return;
        }

        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($fn);
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    /**
     * Emit the build-mode call-site cache for `$node`. After the first
     * dispatch, `$__phel_call_N` holds the resolved `AbstractFn` and
     * subsequent calls in this fn body skip the registry lookup entirely.
     */
    private function emitCachedGlobalFn(CallNode $node, GlobalVarNode $fn, int $slot): void
    {
        $loc = $node->getStartSourceLocation();
        $ns = PhpStringEscape::doubleQuoted($this->outputEmitter->mungeEncodeRegistryKey($fn->getNamespace()));
        $name = PhpStringEscape::doubleQuoted($fn->getName()->getName());

        $this->outputEmitter->emitStr(
            '($__phel_call_' . $slot . ' ??= \\' . Phel::class . '::getDefinition("' . $ns . '", "' . $name . '"))',
            $loc,
        );
    }

    /**
     * A call is a self-call when the callee resolves to the same global fn
     * whose body we are currently emitting. In that case `$this` already
     * references the AbstractFn instance, so we skip the registry lookup.
     *
     * Detection lives in {@see GlobalCallTarget::isSelfCall()} so the
     * call-site cache scanner and the emitter stay aligned. Memoised defs
     * deliberately leave boundTo unset in `DefSymbol`, so self recursion
     * routes through the registry (and therefore the memo wrapper) rather
     * than `$this`.
     */
    private function isSelfCall(CallNode $node): bool
    {
        $fn = $node->getFn();
        return $fn instanceof GlobalVarNode
            && GlobalCallTarget::isSelfCall($fn, $node);
    }

    private function emitFunctionArguments(CallNode $node): void
    {
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitArgList(
            $node->getArguments(),
            $node->getStartSourceLocation(),
        );
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    /**
     * Emits `->__invoke(...)` rather than `->call(...)`: a direct method
     * call on `__invoke` is *not* magic dispatch (magic only fires on the
     * `$obj($args)` syntax), and it preserves the subclass's positional
     * `__invoke` signature, avoiding the variadic-spread cost that the
     * `call(...)` forwarder would introduce on every fn call.
     */
    private function emitCallMethodArguments(CallNode $node): void
    {
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('->__invoke(', $loc);
        $this->outputEmitter->emitArgList($node->getArguments(), $loc);
        $this->outputEmitter->emitStr(')', $loc);
    }
}
