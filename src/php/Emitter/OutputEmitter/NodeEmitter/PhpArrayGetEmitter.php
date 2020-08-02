<?php

declare(strict_types=1);

namespace Phel\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\PhpArrayGetNode;
use Phel\Emitter\OutputEmitter\NodeEmitter;

final class PhpArrayGetEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpArrayGetNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('((', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getArrayExpr());
        $this->outputEmitter->emitStr(')[(', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getAccessExpr());
        $this->outputEmitter->emitStr(')] ?? null)', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
