<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\ThrowNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;
use Phel\NodeEnvironment;

final class ThrowEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof ThrowNode);

        if ($node->getEnv()->getContext() === NodeEnvironment::CONTEXT_EXPRESSION) {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitStr('throw ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getExceptionExpr());
        $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

        if ($node->getEnv()->getContext() === NodeEnvironment::CONTEXT_EXPRESSION) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
