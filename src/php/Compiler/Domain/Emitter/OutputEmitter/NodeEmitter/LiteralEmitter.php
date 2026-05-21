<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class LiteralEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof LiteralNode);

        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            return;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $loc);

        $cached = $this->outputEmitter->emitConstantSlotPrefix($node, $loc);
        $this->outputEmitter->emitLiteral($node->getValue());
        if ($cached) {
            $this->outputEmitter->emitConstantSlotSuffix($loc);
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $loc);
    }
}
