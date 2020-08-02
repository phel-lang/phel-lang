<?php

declare(strict_types=1);

namespace Phel\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\GlobalVarNode;
use Phel\Ast\Node;
use Phel\Emitter\OutputEmitter\NodeEmitter;

final class GlobalVarEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof GlobalVarNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitGlobalBase($node->getNamespace(), $node->getName());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
