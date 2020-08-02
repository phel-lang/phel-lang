<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\DoNode;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class DoEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof DoNode);

        $wrapFn = count($node->getStmts()) > 0 && $node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR;
        if ($wrapFn) {
            $this->emitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getStmts() as $i => $stmt) {
            $this->emitter->emitNode($stmt);
            $this->emitter->emitLine();
        }
        $this->emitter->emitNode($node->getRet());

        if ($wrapFn) {
            $this->emitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
