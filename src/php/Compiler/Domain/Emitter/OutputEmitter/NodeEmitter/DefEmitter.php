<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;

use function assert;

final class DefEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefNode);

        // In cache mode, also register the definition in GlobalEnvironment
        // so the analyzer can resolve symbols when other files have cache misses
        if ($this->outputEmitter->getOptions()->isCacheEmitMode()) {
            $ns = addslashes($this->outputEmitter->mungeEncodeNs($node->getNamespace()));
            $name = addslashes($node->getName()->getName());
            $this->outputEmitter->emitLine('if (!\\' . GlobalEnvironmentSingleton::class . '::getInstance()->hasDefinition("' . $ns . '", \\' . Symbol::class . '::create("' . $name . '"))) {');
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitLine('\\' . GlobalEnvironmentSingleton::class . '::getInstance()->addDefinition(');
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitStr('"');
            $this->outputEmitter->emitStr($ns);
            $this->outputEmitter->emitLine('",');
            $this->outputEmitter->emitLine('\\' . Symbol::class . '::create("' . $name . '")');
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine(');');
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine('}');
        }

        $this->outputEmitter->emitLine('\\Phel::addDefinition(');
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
