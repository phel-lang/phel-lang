<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\LiteralNode;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class LiteralEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof LiteralNode);

        if (!($node->getEnv()->getContext() === NodeEnvironment::CTX_STMT)) {
            $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->emitter->emitLiteral($node->getValue());
            $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
