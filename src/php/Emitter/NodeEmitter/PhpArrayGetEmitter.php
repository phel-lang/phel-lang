<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\PhpArrayGetNode;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;

final class PhpArrayGetEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof PhpArrayGetNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitStr('((', $node->getStartSourceLocation());
        $this->emitter->emitNode($node->getArrayExpr());
        $this->emitter->emitStr(')[(', $node->getStartSourceLocation());
        $this->emitter->emitNode($node->getAccessExpr());
        $this->emitter->emitStr(')] ?? null)', $node->getStartSourceLocation());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
