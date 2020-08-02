<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\ArrayNode;
use Phel\Ast\Node;
use Phel\Emitter;

final class ArrayEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

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
