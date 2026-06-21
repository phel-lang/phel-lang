<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNamedArgNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ContextualWrapEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;
use RuntimeException;

use function assert;

final class PhpObjectCallEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpObjectCallNode);

        $targetExpr = $node->getTargetExpr();

        // A `PhpClassNameNode` target is an inherently static `Class::m(...)`
        // call; like the legacy fast path, it never needed value isolation, so
        // it always emits inline without a temp or an IIFE.
        if ($targetExpr instanceof PhpClassNameNode) {
            $this->emitSimpleTarget($node);

            return;
        }

        // An instance call whose method receives a bare local argument relies
        // on the IIFE's by-value `use($local)` capture as a copy barrier: a
        // by-reference PHP parameter must not write back into the Phel binding
        // (only `(php/ref local)` opts into that). Keep the IIFE in that case,
        // in every context.
        if ($this->hasByValueLocalArg($node)) {
            $this->emitForcedWrappedTarget($node);

            return;
        }

        if ($targetExpr instanceof LocalVarNode) {
            $this->emitSimpleTarget($node);

            return;
        }

        $this->emitComputedTarget($node);
    }

    /**
     * Simple target (`LocalVarNode` / `PhpClassNameNode`): no temp var and no
     * IIFE in any context. Emits `(<target><fn><call>)` directly.
     */
    private function emitSimpleTarget(PhpObjectCallNode $node): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->emitTarget($node);
        $this->outputEmitter->emitStr($this->fnCode($node), $node->getStartSourceLocation());
        $this->emitCall($node);
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    /**
     * Computed target: a `target_` temp guarantees exactly-once target
     * evaluation. The shared kernel wraps it in an IIFE only in expression
     * context (where a `return` is needed); in statement/return context it
     * emits plain statements.
     */
    private function emitComputedTarget(PhpObjectCallNode $node): void
    {
        $targetSym = Symbol::gen('target_');

        new ContextualWrapEmitter($this->outputEmitter)->emit(
            $node,
            function () use ($node, $targetSym): void {
                $this->emitTempAssignment($node, $targetSym);
            },
            function () use ($node, $targetSym): void {
                $this->emitTargetCall($node, $targetSym);
            },
        );
    }

    /**
     * Forced IIFE: the by-value `use($local)` capture is a copy barrier that
     * must hold in every context, so the closure is emitted regardless of
     * whether the result is consumed.
     */
    private function emitForcedWrappedTarget(PhpObjectCallNode $node): void
    {
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitFnWrapPrefix(
            $node->getEnv(),
            $node->getStartSourceLocation(),
            new ByRefLocalCollector()->collect($node),
        );

        $targetSym = Symbol::gen('target_');
        $this->emitTempAssignment($node, $targetSym);

        $this->outputEmitter->emitStr('return ', $node->getStartSourceLocation());
        $this->emitTargetCall($node, $targetSym);
        $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    private function emitTempAssignment(PhpObjectCallNode $node, Symbol $targetSym): void
    {
        $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->emitTarget($node);
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
    }

    private function emitTargetCall(PhpObjectCallNode $node, Symbol $targetSym): void
    {
        $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr($this->fnCode($node), $node->getStartSourceLocation());
        $this->emitCall($node);
    }

    private function emitTarget(PhpObjectCallNode $node): void
    {
        $targetExpr = $node->getTargetExpr();

        if ($targetExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr(
                $targetExpr->getAbsolutePhpName(),
                $targetExpr->getName()->getStartLocation(),
            );

            return;
        }

        $this->outputEmitter->emitNode($targetExpr);
    }

    private function emitCall(PhpObjectCallNode $node): void
    {
        $callExpr = $node->getCallExpr();

        if ($callExpr instanceof MethodCallNode) {
            $this->outputEmitter->emitStr($callExpr->getFn()->getName(), $callExpr->getFn()->getStartLocation());
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitArgList($callExpr->getArgs(), $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        } elseif ($callExpr instanceof PropertyOrConstantAccessNode) {
            $this->outputEmitter->emitStr($callExpr->getName()->getName(), $callExpr->getName()->getStartLocation());
        } else {
            throw new RuntimeException('Not supported ' . $callExpr::class);
        }
    }

    /**
     * Whether the call passes a bare local variable as a top-level method
     * argument. PHP binds a by-reference parameter only to a variable handed
     * in directly (`$obj->m($x)`), so only a top-level `LocalVarNode` (or a
     * named argument wrapping one) can leak a write back into a Phel binding.
     * Such args need the IIFE's by-value `use(...)` copy barrier; `php/ref`
     * args (a `PhpRefNode`) opt into the write-back and are excluded.
     */
    private function hasByValueLocalArg(PhpObjectCallNode $node): bool
    {
        $callExpr = $node->getCallExpr();
        if (!$callExpr instanceof MethodCallNode) {
            return false;
        }

        foreach ($callExpr->getArgs() as $arg) {
            if ($arg instanceof PhpNamedArgNode) {
                $arg = $arg->getValueExpr();
            }

            if ($arg instanceof LocalVarNode) {
                return true;
            }
        }

        return false;
    }

    private function fnCode(PhpObjectCallNode $node): string
    {
        return $node->isStatic() ? '::' : '->';
    }
}
