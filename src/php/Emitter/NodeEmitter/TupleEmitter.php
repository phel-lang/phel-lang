<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\TupleNode;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;

final class TupleEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof TupleNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitStr('\Phel\Lang\Tuple::createBracket(', $node->getStartSourceLocation());
        $this->emitter->emitArgList($node->getArgs(), $node->getStartSourceLocation());
        $this->emitter->emitStr(')', $node->getStartSourceLocation());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
