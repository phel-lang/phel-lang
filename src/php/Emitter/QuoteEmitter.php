<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;
use Phel\Ast\QuoteNode;
use Phel\Emitter;

final class QuoteEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof QuoteNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitPhel($node->getValue());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
