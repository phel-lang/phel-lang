<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class VectorEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof VectorNode);
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $loc);
        $cached = $this->outputEmitter->emitConstantSlotPrefix($node, $loc);
        $this->outputEmitter->emitStr('\Phel::vector([', $loc);
        $this->outputEmitter->emitArgList($node->getArgs(), $loc);
        $this->outputEmitter->emitStr('])', $loc);
        if ($cached) {
            $this->outputEmitter->emitConstantSlotSuffix($loc);
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $loc);
    }
}
