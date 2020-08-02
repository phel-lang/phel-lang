<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\ArrayNode;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitter;

final class ArrayEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof ArrayNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitStr('\Phel\Lang\PhelArray::create(', $node->getStartSourceLocation());
        $this->emitter->emitArgList($node->getValues(), $node->getStartSourceLocation());
        $this->emitter->emitStr(')', $node->getStartSourceLocation());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
