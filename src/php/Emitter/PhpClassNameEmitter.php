<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;
use Phel\Ast\PhpClassNameNode;
use Phel\Emitter;

final class PhpClassNameEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof PhpClassNameNode);

        $this->emitter->emitStr(
            $node->getName()->getName(),
            $node->getName()->getStartLocation()
        );
    }
}
