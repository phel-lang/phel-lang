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
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\AssocConjCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\AtomMethodCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\CoreFnCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\GetInCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\NilBooleanCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\NumericOperationCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\ReduceCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\SpecializedCallEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\TypedCollectionCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\TypedValueCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized\TypePredicateCallEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpStringEscape;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Symbol;

use function assert;
use function count;

final readonly class CallEmitter implements NodeEmitterInterface
{
    /** @var list<SpecializedCallEmitterInterface> */
    private array $specializedEmitters;

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {
        // Ordered chain of call-site specialisation families. Each family
        // returns false when its eligibility predicates do not match, so the
        // node falls through to the generic dispatch below. The predicates
        // across families are disjoint (distinct fn names, arities, and
        // analyser tags), so the order between families is not significant.
        $this->specializedEmitters = [
            new TypedValueCallEmitter($outputEmitter),
            new CoreFnCallEmitter($outputEmitter),
            new GetInCallEmitter($outputEmitter),
            new NilBooleanCallEmitter($outputEmitter),
            new TypePredicateCallEmitter($outputEmitter),
            new AtomMethodCallEmitter($outputEmitter),
            new TypedCollectionCallEmitter($outputEmitter),
            new AssocConjCallEmitter($outputEmitter),
            new NumericOperationCallEmitter($outputEmitter),
            new ReduceCallEmitter($outputEmitter),
        ];
    }

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof CallNode);

        $fnNode = $node->getFn();
        $isYield = $fnNode instanceof PhpVarNode && $fnNode->getName() === 'yield';

        if (!$isYield) {
            $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
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

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
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

        foreach ($this->specializedEmitters as $specializedEmitter) {
            if ($specializedEmitter->tryEmit($node)) {
                return;
            }
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
