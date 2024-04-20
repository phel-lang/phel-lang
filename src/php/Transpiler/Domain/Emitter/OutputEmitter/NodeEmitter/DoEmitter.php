<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Ast\DoNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class DoEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DoNode);

        $isWrapFn = $node->getStmts() !== [] && $node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION);

        if ($isWrapFn) {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getStmts() as $stmt) {
            $this->outputEmitter->emitNode($stmt);
            $this->outputEmitter->emitLine();
        }

        $this->outputEmitter->emitNode($node->getRet());

        if ($isWrapFn) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
