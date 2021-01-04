<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\DefNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class DefEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefNode);

        $this->outputEmitter->emitGlobalBase($node->getNamespace(), $node->getName());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getInit());
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        if (count($node->getMeta()) > 0) {
            $this->outputEmitter->emitGlobalBaseMeta($node->getNamespace(), $node->getName());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitLiteral($node->getMeta());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }
    }
}
