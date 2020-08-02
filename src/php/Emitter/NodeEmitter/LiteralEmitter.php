<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\LiteralNode;
use Phel\Ast\Node;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class LiteralEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof LiteralNode);

        if (!($node->getEnv()->getContext() === NodeEnvironment::CTX_STMT)) {
            $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->emitter->emitScalarAndAbstractType($node->getValue());
            $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
