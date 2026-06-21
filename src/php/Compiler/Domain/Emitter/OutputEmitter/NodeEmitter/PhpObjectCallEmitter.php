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
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
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
        // (only `(php/ref local)` opts into that). Keep the IIFE in that case.
        if ($this->hasByValueLocalArg($node)) {
            $this->emitWrappedTarget($node);

            return;
        }

        if ($targetExpr instanceof LocalVarNode) {
            $this->emitSimpleTarget($node);

            return;
        }

        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->emitWrappedTarget($node);

            return;
        }

        $this->emitStatementTarget($node);
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
     * Computed target in expression context: wrap in an IIFE so the temp var
     * (which guarantees exactly-once target evaluation) can host a `return`.
     */
    private function emitWrappedTarget(PhpObjectCallNode $node): void
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

    /**
     * Computed target in statement/return context: emit plain statements
     * (`$target_N = <target>;` then the call) without an IIFE. The temp var
     * still guarantees exactly-once target evaluation.
     */
    private function emitStatementTarget(PhpObjectCallNode $node): void
    {
        $targetSym = Symbol::gen('target_');
        $this->emitTempAssignment($node, $targetSym);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitTargetCall($node, $targetSym);
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
