<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

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
            $node->getName()->getStartLocation(),
        );
        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getBody());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
    }
}
