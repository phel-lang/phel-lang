<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;

use function addslashes;
use function assert;

final class InNsEmitter implements NodeEmitterInterface
{
    use NsStateDefinitionsEmitterTrait;
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof InNsNode);

        $this->outputEmitter->emitLine(
            '\\' . GlobalEnvironmentSingleton::class . '::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");',
            $node->getStartSourceLocation(),
        );

        // Update *file* definition to ensure subsequent loads resolve relative paths correctly
        $this->emitFileAndNsDefinitions(
            $node->getNamespace(),
            $node->getStartSourceLocation()?->getFile() ?? '',
        );
    }
}
