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

        $this->outputEmitter->emitLine('\\Phel\\Lang\\Registry::getInstance()->addDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs($node->getNamespace())));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($node->getName()->getName()));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitNode($node->getInit());
        if (count($node->getMeta()->getKeyValues()) > 0) {
            $this->outputEmitter->emitLine(',');
            $this->outputEmitter->emitNode($node->getMeta());
        }
        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');
    }
}
