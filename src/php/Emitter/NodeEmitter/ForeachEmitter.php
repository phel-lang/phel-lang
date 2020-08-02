<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\ForeachNode;
use Phel\Ast\Node;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class ForeachEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof ForeachNode);

        if ($node->getEnv()->getContext() !== NodeEnvironment::CTX_STMT) {
            $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitStr('foreach ((', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getListExpr());
        $this->outputEmitter->emitStr(' ?? []) as ', $node->getStartSourceLocation());
        if ($node->getKeySymbol()) {
            $this->outputEmitter->emitPhpVariable($node->getKeySymbol());
            $this->outputEmitter->emitStr(' => ', $node->getStartSourceLocation());
        }
        $this->outputEmitter->emitPhpVariable($node->getValueSymbol());
        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getBodyExpr());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

        if ($node->getEnv()->getContext() !== NodeEnvironment::CTX_STMT) {
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitStr('return null;', $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
            $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
