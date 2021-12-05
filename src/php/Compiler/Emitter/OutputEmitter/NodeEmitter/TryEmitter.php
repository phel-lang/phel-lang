<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\TryNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class TryEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof TryNode);

        if (!$node->getFinally() && count($node->getCatches()) === 0) {
            $this->outputEmitter->emitNode($node->getBody());
            return;
        }

        if ($node->getEnv()->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION) {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->emitTry($node);
        $this->emitCatch($node);
        $this->emitFinally($node);

        if ($node->getEnv()->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }

    private function emitTry(TryNode $node): void
    {
        $this->outputEmitter->emitLine('try {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getBody());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
    }

    private function emitCatch(TryNode $node): void
    {
        foreach ($node->getCatches() as $catchNode) {
            $this->outputEmitter->emitNode($catchNode);
        }
    }

    private function emitFinally(TryNode $node): void
    {
        $finally = $node->getFinally();
        if (!$finally) {
            return;
        }

        $this->outputEmitter->emitLine(' finally {', $finally->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($finally);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $finally->getStartSourceLocation());
    }
}
