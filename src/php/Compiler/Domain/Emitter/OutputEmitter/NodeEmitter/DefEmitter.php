<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

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
        if ($node->getMeta()->getKeyValues() !== []) {
            $this->outputEmitter->emitLine(',');
            $this->outputEmitter->emitNode($node->getMeta());
        }

        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');
    }
}
