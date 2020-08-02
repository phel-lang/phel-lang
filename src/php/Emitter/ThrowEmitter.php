<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\DoNode;
use Phel\Ast\Node;
use Phel\Ast\ThrowNode;
use Phel\Emitter;
use Phel\NodeEnvironment;

final class ThrowEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof ThrowNode);

        if ($node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR) {
            $this->emitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->emitter->emitStr('throw ', $node->getStartSourceLocation());
        $this->emitter->emit($node->getExceptionExpr());
        $this->emitter->emitStr(';', $node->getStartSourceLocation());

        if ($node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR) {
            $this->emitter->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
