<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\CatchNode;
use Phel\Ast\Node;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

final class CatchEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
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
