<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;
use Phel\Ast\PhpArraySetNode;
use Phel\Emitter;

final class PhpArraySetEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof PhpArraySetNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitStr('(', $node->getStartSourceLocation());
        $this->emitter->emit($node->getArrayExpr());
        $this->emitter->emitStr(')[(', $node->getStartSourceLocation());
        $this->emitter->emit($node->getAccessExpr());
        $this->emitter->emitStr(')] = ', $node->getStartSourceLocation());
        $this->emitter->emit($node->getValueExpr());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
