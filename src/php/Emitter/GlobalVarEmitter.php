<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\GlobalVarNode;
use Phel\Ast\Node;
use Phel\Emitter;

final class GlobalVarEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof GlobalVarNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitGlobalBase($node->getNamespace(), $node->getName());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
