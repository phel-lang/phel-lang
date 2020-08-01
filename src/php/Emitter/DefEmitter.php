<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\DefNode;
use Phel\Ast\Node;
use Phel\Emitter;

final class DefEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof DefNode);
        $this->emitter->emitGlobalBase($node->getNamespace(), $node->getName());
        $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->emitter->emit($node->getInit());
        $this->emitter->emitLine(';', $node->getStartSourceLocation());

        if (count($node->getMeta()) > 0) {
            $this->emitter->emitGlobalBaseMeta($node->getNamespace(), $node->getName());
            $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->emitter->emitPhel($node->getMeta());
            $this->emitter->emitLine(';', $node->getStartSourceLocation());
        }
    }
}
