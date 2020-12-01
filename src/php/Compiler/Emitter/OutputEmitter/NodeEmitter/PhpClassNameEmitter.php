<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\Node;
use Phel\Compiler\Ast\PhpClassNameNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

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
