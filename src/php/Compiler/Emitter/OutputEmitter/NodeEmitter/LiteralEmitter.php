<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\LiteralNode;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\NodeEnvironmentInterface;

final class LiteralEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof LiteralNode);

        if (!($node->getEnv()->getContext() === NodeEnvironmentInterface::CONTEXT_STATEMENT)) {
            $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->outputEmitter->emitLiteral($node->getValue());
            $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
