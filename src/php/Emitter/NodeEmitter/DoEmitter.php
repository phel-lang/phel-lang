<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\DoNode;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class DoEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof DoNode);

        $wrapFn = count($node->getStmts()) > 0 && $node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR;
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
