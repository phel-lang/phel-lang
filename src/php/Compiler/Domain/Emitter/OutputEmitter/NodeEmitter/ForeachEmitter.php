<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
use Phel\Compiler\Domain\Emitter\OutputEmitter\IterableTarget;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\YieldDetector;
use Phel\Lang\Seq;
use Phel\Lang\Symbol;

use function assert;

final class ForeachEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof ForeachNode);

        $env = $node->getEnv();
        // A foreach lowers to PHP statements, so expression context still needs
        // the IIFE to host its (always-nil) value inline. Return context runs the
        // loop as plain statements and returns nil after — unless the body yields:
        // there the IIFE is the generator boundary, and eliding it would promote
        // the enclosing fn to a generator, deferring its pre-loop side effects.
        // Statement context discards the value and is never wrapped.
        $isReturn = $env->isContext(NodeEnvironment::CONTEXT_RETURN);
        $needsWrap = $env->isContext(NodeEnvironment::CONTEXT_EXPRESSION)
            || ($isReturn && new YieldDetector()->containsYield($node));

        if ($needsWrap) {
            $this->outputEmitter->emitContextPrefix($env, $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapPrefix(
                $env,
                $node->getStartSourceLocation(),
                new ByRefLocalCollector()->collect($node),
            );
        }

        // `Seq::toIterable` is only needed to coerce `nil` to `[]` and
        // unwrap strings; for nodes the emitter has already proven are
        // iterable, the adapter call is a no-op we skip.
        $listExpr = $node->getListExpr();
        $useAdapter = !IterableTarget::isIterable($listExpr);

        if ($useAdapter) {
            $this->outputEmitter->emitStr('foreach (\\' . Seq::class . '::toIterable(', $node->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitStr('foreach (', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitNode($listExpr);

        $this->outputEmitter->emitStr($useAdapter ? ') as ' : ' as ', $node->getStartSourceLocation());
        if ($node->getKeySymbol() instanceof Symbol) {
            $this->outputEmitter->emitPhpVariable($node->getKeySymbol());
            $this->outputEmitter->emitStr(' => ', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitPhpVariable($node->getValueSymbol());
        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getBodyExpr());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

        // The foreach's value is always nil; emit it wherever a value is wanted.
        if ($needsWrap || $isReturn) {
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitStr('return null;', $node->getStartSourceLocation());
        }

        if ($needsWrap) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
            $this->outputEmitter->emitContextSuffix($env, $node->getStartSourceLocation());
        }
    }
}
