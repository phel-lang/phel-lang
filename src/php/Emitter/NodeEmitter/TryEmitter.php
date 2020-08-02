<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\TryNode;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class TryEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof TryNode);

        if ($node->getFinally() || count($node->getCatches()) > 0) {
            if ($node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR) {
                $this->emitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
            }

            $this->emitter->emitLine('try {', $node->getStartSourceLocation());
            $this->emitter->increaseIndentLevel();
            $this->emitter->emitNode($node->getBody());
            $this->emitter->decreaseIndentLevel();
            $this->emitter->emitLine();
            $this->emitter->emitStr('}', $node->getStartSourceLocation());

            foreach ($node->getCatches() as $catchNode) {
                $this->emitter->emitNode($catchNode);
            }

            if ($node->getFinally()) {
                $this->emitFinally($node->getFinally());
            }

            if ($node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR) {
                $this->emitter->emitFnWrapSuffix($node->getStartSourceLocation());
            }
        } else {
            $this->emitter->emitNode($node->getBody());
        }
    }

    private function emitFinally(Node $node): void
    {
        $this->emitter->emitLine(' finally {', $node->getStartSourceLocation());
        $this->emitter->increaseIndentLevel();
        $this->emitter->emitNode($node);
        $this->emitter->decreaseIndentLevel();
        $this->emitter->emitLine();
        $this->emitter->emitStr('}', $node->getStartSourceLocation());
    }
}
