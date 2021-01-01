<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Ast\PhpClassNameNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class PhpClassNameEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpClassNameNode);

        $this->outputEmitter->emitStr(
            $node->getName()->getName(),
            $node->getName()->getStartLocation()
        );
    }
}
