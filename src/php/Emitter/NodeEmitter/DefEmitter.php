<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\DefNode;
use Phel\Ast\Node;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;

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
        $this->emitter->emitNode($node->getInit());
        $this->emitter->emitLine(';', $node->getStartSourceLocation());

        if (count($node->getMeta()) > 0) {
            $this->emitter->emitGlobalBaseMeta($node->getNamespace(), $node->getName());
            $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->emitter->emitScalarAndAbstractType($node->getMeta());
            $this->emitter->emitLine(';', $node->getStartSourceLocation());
        }
    }
}
