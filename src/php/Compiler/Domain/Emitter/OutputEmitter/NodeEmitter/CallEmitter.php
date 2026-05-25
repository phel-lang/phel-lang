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

        if ($this->tryEmitTypedVectorAccessor($node)) {
            return;
        }

        if ($this->tryEmitTypedBinaryOp($node)) {
            return;
        }

        $useCallMethod = !$this->isSelfCall($node) && GlobalCallTarget::isGlobalFnCall($node);

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
