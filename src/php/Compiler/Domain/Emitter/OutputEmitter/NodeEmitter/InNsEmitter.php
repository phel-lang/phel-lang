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
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof InNsNode);

        // Set the namespace in the global environment
        $this->outputEmitter->emitLine(
            GlobalEnvironmentSingleton::class . '::getInstance()->setNs("' . addslashes($node->getNamespace()) . '");',
            $node->getStartSourceLocation(),
        );
    }
}
