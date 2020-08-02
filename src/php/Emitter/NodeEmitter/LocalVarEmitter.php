<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\LocalVarNode;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitter;

final class LocalVarEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof LocalVarNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitPhpVariable($node->getName());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
