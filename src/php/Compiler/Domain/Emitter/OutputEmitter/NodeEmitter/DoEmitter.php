<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class DoEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DoNode);

        // Wrap in an IIFE whenever we need to host a `return X;` inside an
        // expression context. `stmts !== []` is the original signal: when the
        // body has non-tail statements, `DoSymbol::analyze` promotes the tail
        // expression to RETURN context for the emit step. The simplifier
        // (`Simplification\DoSimplifier`) may drop every non-tail statement,
        // so the ret's env is the authoritative source of truth, not the
        // current `stmts` count.
        $isExpression = $node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION);
        $retNeedsReturn = $node->getRet()->getEnv()->isContext(NodeEnvironment::CONTEXT_RETURN);
        $isWrapFn = $isExpression && ($node->getStmts() !== [] || $retNeedsReturn);

        if ($isWrapFn) {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getStmts() as $stmt) {
            $this->outputEmitter->emitNode($stmt);
            $this->outputEmitter->emitLine();
        }

        $this->outputEmitter->emitNode($node->getRet());

        if ($isWrapFn) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
