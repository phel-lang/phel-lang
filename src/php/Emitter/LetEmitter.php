<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\LetNode;
use Phel\Ast\Node;
use Phel\Emitter;
use Phel\NodeEnvironment;

final class LetEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

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
            $this->emitter->emit($binding->getInitExpr());
            $this->emitter->emitLine(';', $node->getStartSourceLocation());
        }

        if ($node->isLoop()) {
            $this->emitter->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->emitter->indentLevel++;
        }

        $this->emitter->emit($node->getBodyExpr());

        if ($node->isLoop()) {
            $this->emitter->emitLine('break;', $node->getStartSourceLocation());
            $this->emitter->indentLevel--;
            $this->emitter->emitStr('}', $node->getStartSourceLocation());
        }

        if ($wrapFn) {
            $this->emitter->emitFnWrapSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
