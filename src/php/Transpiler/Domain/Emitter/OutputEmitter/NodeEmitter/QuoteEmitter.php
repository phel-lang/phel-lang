<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class QuoteEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof QuoteNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitLiteral($node->getValue());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
