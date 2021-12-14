<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\CatchNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class CatchEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof CatchNode);

        $this->outputEmitter->emitStr(' catch (', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getType());
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
