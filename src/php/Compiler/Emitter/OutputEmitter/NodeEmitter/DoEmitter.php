<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\DoNode;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;
use Phel\Compiler\NodeEnvironmentInterface;

final class DoEmitter implements NodeEmitter
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DoNode);

        $wrapFn = count($node->getStmts()) > 0 && $node->getEnv()->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION;
        if ($wrapFn) {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getStmts() as $i => $stmt) {
            $this->outputEmitter->emitNode($stmt);
            $this->outputEmitter->emitLine();
        }
        $this->outputEmitter->emitNode($node->getRet());

        if ($wrapFn) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
