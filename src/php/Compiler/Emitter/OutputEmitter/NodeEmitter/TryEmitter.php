<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\TryNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;

final class TryEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof TryNode);

        if ($node->getFinally() || count($node->getCatches()) > 0) {
            if ($node->getEnv()->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION) {
                $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
            }

            $this->outputEmitter->emitLine('try {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
            $this->outputEmitter->emitNode($node->getBody());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

            foreach ($node->getCatches() as $catchNode) {
                $this->outputEmitter->emitNode($catchNode);
            }

            if ($node->getFinally()) {
                $this->emitFinally($node->getFinally());
            }

            if ($node->getEnv()->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION) {
                $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
            }
        } else {
            $this->outputEmitter->emitNode($node->getBody());
        }
    }

    private function emitFinally(AbstractNode $node): void
    {
        $this->outputEmitter->emitLine(' finally {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node);
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
    }
}
