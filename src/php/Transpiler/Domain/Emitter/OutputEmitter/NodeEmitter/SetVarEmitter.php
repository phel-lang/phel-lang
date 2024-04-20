<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class SetVarEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof SetVarNode);
        $symbolNode = $node->getSymbol();
        assert($symbolNode instanceof GlobalVarNode);

        $this->outputEmitter->emitLine('\\Phel\\Lang\\Registry::getInstance()->addDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs($symbolNode->getNamespace())));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($symbolNode->getName()->getName()));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitNode($node->getValueExpr());
        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');
    }
}
