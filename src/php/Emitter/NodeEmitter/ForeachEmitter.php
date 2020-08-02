<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\ForeachNode;
use Phel\Ast\Node;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;
use Phel\NodeEnvironment;

final class ForeachEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof ForeachNode);

        if ($node->getEnv()->getContext() !== NodeEnvironment::CTX_STMT) {
            $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->emitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->emitter->emitStr('foreach ((', $node->getStartSourceLocation());
        $this->emitter->emit($node->getListExpr());
        $this->emitter->emitStr(' ?? []) as ', $node->getStartSourceLocation());
        if ($node->getKeySymbol()) {
            $this->emitter->emitPhpVariable($node->getKeySymbol());
            $this->emitter->emitStr(' => ', $node->getStartSourceLocation());
        }
        $this->emitter->emitPhpVariable($node->getValueSymbol());
        $this->emitter->emitLine(') {', $node->getStartSourceLocation());
        $this->emitter->indentLevel++;
        $this->emitter->emit($node->getBodyExpr());
        $this->emitter->indentLevel--;
        $this->emitter->emitLine();
        $this->emitter->emitStr('}', $node->getStartSourceLocation());

        if ($node->getEnv()->getContext() !== NodeEnvironment::CTX_STMT) {
            $this->emitter->emitLine();
            $this->emitter->emitStr('return null;', $node->getStartSourceLocation());
            $this->emitter->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
            $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
