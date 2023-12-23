<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class GlobalVarEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof GlobalVarNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        if ($node->useReference()) {
            $this->outputEmitter->emitStr('\\Phel\\Lang\\Registry::getInstance()->getDefinitionReference("');
        } else {
            $this->outputEmitter->emitStr('\\Phel\\Lang\\Registry::getInstance()->getDefinition("');
        }

        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs($node->getNamespace())));
        $this->outputEmitter->emitStr('", "');
        $this->outputEmitter->emitStr(addslashes($node->getName()->getName()));
        $this->outputEmitter->emitStr('")');
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
