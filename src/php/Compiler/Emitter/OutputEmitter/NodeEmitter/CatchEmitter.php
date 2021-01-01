<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\CatchNode;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

final class CatchEmitter implements NodeEmitter
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof CatchNode);

        $this->outputEmitter->emitStr(' catch (', $node->getStartSourceLocation());
        $this->outputEmitter->emitStr($node->getType()->getName(), $node->getType()->getStartLocation());
        $this->outputEmitter->emitStr(
            ' $' . $this->outputEmitter->mungeEncode($node->getName()->getName()),
            $node->getName()->getStartLocation()
        );
        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getBody());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
    }
}
