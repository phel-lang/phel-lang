<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Symbol;

use function assert;
use function count;

final readonly class FnAsClassEmitter implements NodeEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private MethodEmitter $methodEmitter,
        private ClosureEmitterHelper $closureHelper,
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof FnNode);

        if ($node->isDefinition()) {
            $this->emitAsClass($node);
        } else {
            $this->emitAsClosure($node);
        }
    }

    private function emitAsClosure(FnNode $node): void
    {
        // Multi-arity children are emitted as bare closures inside the parent
        // MultiFnNode's constructor. Their name binds to the outer class
        // instance via `$name = $this;` injected at the top of the body; the
        // IIFE self-binding path is used only for standalone named fns.
        $name = $node->getName();
        if ($name instanceof Symbol && !$node->isMultiArityChild()) {
            $this->emitAsNamedClosure($node, $name);
            return;
        }

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('(function(', $node->getStartSourceLocation());

        $this->methodEmitter->emitParameters($node);
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        $this->emitUseClause($node);
        $this->outputEmitter->emitLine(' {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $this->methodEmitter->emitBody($node);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('})', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    /**
     * A named `(fn name [params] body)` compiles to an IIFE that assigns the
     * closure to a local variable captured by reference, so the body can refer
     * to `name` for self-recursion:
     *
     *   (function() use ($captures) {
     *     $name = function($params) use (&$name, $captures) { ... body ... };
     *     return $name;
     *   })()
     *
     * The outer wrapper captures the enclosing scope's closed-over vars by
     * value; the inner closure then re-captures the self-reference by
     * reference alongside those same vars.
     */
    private function emitAsNamedClosure(FnNode $node, Symbol $name): void
    {
        $nameVar = '$' . $this->outputEmitter->mungeEncode($name->getName());
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $loc);
        $this->outputEmitter->emitStr('(function()', $loc);
        $this->emitOuterUseClause($node);
        $this->outputEmitter->emitLine(' {', $loc);
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitStr($nameVar . ' = function(', $loc);

        $this->methodEmitter->emitParameters($node);
        $this->outputEmitter->emitStr(')', $loc);
        $this->emitUseClauseWithSelfReference($node, $nameVar);
        $this->outputEmitter->emitLine(' {', $loc);
        $this->outputEmitter->increaseIndentLevel();

        $this->methodEmitter->emitBody($node);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitLine('};', $loc);
        $this->outputEmitter->emitLine('return ' . $nameVar . ';', $loc);

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('})()', $loc);
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $loc);
    }

    private function emitUseClause(FnNode $node): void
    {
        $uses = $node->getUses();
        if ($uses === []) {
            return;
        }

        $this->outputEmitter->emitStr(' use(', $node->getStartSourceLocation());
        foreach ($uses as $i => $use) {
            $shadowed = $node->getEnv()->getShadowed($use);
            $normalizedUse = $shadowed instanceof Symbol ? $shadowed : $use;
            $this->outputEmitter->emitStr('$' . $this->outputEmitter->mungeEncode($normalizedUse->getName()), $node->getStartSourceLocation());
            if ($i < count($uses) - 1) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    /**
     * Outer wrapper of the named closure IIFE: captures the enclosing scope's
     * uses by value so they're visible to the assignment and the inner closure.
     */
    private function emitOuterUseClause(FnNode $node): void
    {
        $this->emitUseClause($node);
    }

    /**
     * Inner closure of the named-fn emission: captures `$name` by reference so
     * the closure can call itself, plus the enclosing scope's uses by value.
     */
    private function emitUseClauseWithSelfReference(FnNode $node, string $nameVar): void
    {
        $uses = $node->getUses();

        $this->outputEmitter->emitStr(' use(&' . $nameVar, $node->getStartSourceLocation());
        foreach ($uses as $use) {
            $shadowed = $node->getEnv()->getShadowed($use);
            $normalizedUse = $shadowed instanceof Symbol ? $shadowed : $use;
            $this->outputEmitter->emitStr(
                ', $' . $this->outputEmitter->mungeEncode($normalizedUse->getName()),
                $node->getStartSourceLocation(),
            );
        }

        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function emitAsClass(FnNode $node): void
    {
        $this->emitClassBegin($node);
        $this->emitProperties($node);
        $this->emitConstructor($node);
        $this->emitInvoke($node);
        $this->emitClassEnd($node);
    }

    private function emitClassBegin(FnNode $node): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('new class(', $node->getStartSourceLocation());

        $this->closureHelper->emitConstructorArguments($node->getUses(), $node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitLine(') extends \Phel\Lang\AbstractFn {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->enterClassScope();
    }

    private function emitProperties(FnNode $node): void
    {
        $ns = addslashes($this->outputEmitter->mungeEncodeNs($node->getEnv()->getBoundTo()));
        $this->outputEmitter->emitLine('public const BOUND_TO = "' . $ns . '";', $node->getStartSourceLocation());

        $this->closureHelper->emitProperties($node->getUses(), $node->getEnv(), $node->getStartSourceLocation());
    }

    private function emitConstructor(FnNode $node): void
    {
        $this->closureHelper->emitConstructor($node->getUses(), $node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitLine();
    }

    private function emitInvoke(FnNode $node): void
    {
        $this->methodEmitter->emit('__invoke', $node);
    }

    private function emitClassEnd(FnNode $node): void
    {
        $this->outputEmitter->exitClassScope();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
