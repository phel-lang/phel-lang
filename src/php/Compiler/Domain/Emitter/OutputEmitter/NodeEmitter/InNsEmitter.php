<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

use function addslashes;
use function assert;

final class InNsEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof InNsNode);

        // Set the namespace in the global environment
        $this->outputEmitter->emitLine(
            GlobalEnvironmentSingleton::class . '::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");',
            $node->getStartSourceLocation(),
        );

        // Update *file* definition to ensure subsequent loads resolve relative paths correctly
        $this->outputEmitter->emitLine(Phel::class . '::addDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs('phel\\core')));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes('*file*'));
        $this->outputEmitter->emitLine('",');

        $file = $node->getStartSourceLocation()?->getFile() ?? '';
        $this->outputEmitter->emitLiteral($file);
        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');

        // Update *ns* definition
        $this->outputEmitter->emitLine(Phel::class . '::addDefinition(');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes($this->outputEmitter->mungeEncodeNs('phel\\core')));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitStr('"');
        $this->outputEmitter->emitStr(addslashes('*ns*'));
        $this->outputEmitter->emitLine('",');
        $this->outputEmitter->emitLiteral($this->outputEmitter->mungeEncodeNs($node->getNamespace()));
        $this->outputEmitter->emitLine();
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine(');');
    }
}
