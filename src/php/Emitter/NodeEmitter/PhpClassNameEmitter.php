<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\PhpClassNameNode;
use Phel\Emitter\NodeEmitter;

final class PhpClassNameEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpClassNameNode);

        $this->outputEmitter->emitStr(
            $node->getName()->getName(),
            $node->getName()->getStartLocation()
        );
    }
}
