<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class ThrowEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof ThrowNode);

        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitStr('throw ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getExceptionExpr());
        $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION)) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
