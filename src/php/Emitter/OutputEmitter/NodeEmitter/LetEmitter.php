<?php

declare(strict_types=1);

namespace Phel\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\LetNode;
use Phel\Ast\Node;
use Phel\Emitter\OutputEmitter\NodeEmitter;
use Phel\NodeEnvironment;

final class LetEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof LetNode);

        $wrapFn = $node->getEnv()->getContext() === NodeEnvironment::CTX_EXPR;
        if ($wrapFn) {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getBindings() as $binding) {
            $this->outputEmitter->emitPhpVariable($binding->getShadow(), $binding->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($binding->getInitExpr());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        if ($node->isLoop()) {
            $this->outputEmitter->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
        }

        $this->outputEmitter->emitNode($node->getBodyExpr());

        if ($node->isLoop()) {
            $this->outputEmitter->emitLine('break;', $node->getStartSourceLocation());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
        }

        if ($wrapFn) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
