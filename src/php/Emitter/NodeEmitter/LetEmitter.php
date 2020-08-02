<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\LetNode;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class LetEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof LetNode);

        $wrapFn = $node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR;
        if ($wrapFn) {
            $this->emitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getBindings() as $binding) {
            $this->emitter->emitPhpVariable($binding->getShadow(), $binding->getStartSourceLocation());
            $this->emitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->emitter->emitNode($binding->getInitExpr());
            $this->emitter->emitLine(';', $node->getStartSourceLocation());
        }

        if ($node->isLoop()) {
            $this->emitter->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->emitter->increaseIndentLevel();
        }

        $this->emitter->emitNode($node->getBodyExpr());

        if ($node->isLoop()) {
            $this->emitter->emitLine('break;', $node->getStartSourceLocation());
            $this->emitter->decreaseIndentLevel();
            $this->emitter->emitStr('}', $node->getStartSourceLocation());
        }

        if ($wrapFn) {
            $this->emitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
