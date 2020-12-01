<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\LiteralNode;
use Phel\Compiler\Ast\Node;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;
use Phel\Compiler\NodeEnvironment;

final class LiteralEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof LiteralNode);

        if (!($node->getEnv()->getContext() === NodeEnvironment::CONTEXT_STATEMENT)) {
            $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->outputEmitter->emitLiteral($node->getValue());
            $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
