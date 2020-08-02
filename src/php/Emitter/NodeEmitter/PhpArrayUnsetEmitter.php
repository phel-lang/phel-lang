<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\PhpArrayUnsetNode;
use Phel\Emitter\NodeEmitter;

final class PhpArrayUnsetEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpArrayUnsetNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitStr('unset((', $node->getStartSourceLocation());
        $this->emitter->emitNode($node->getArrayExpr());
        $this->emitter->emitStr(')[(', $node->getStartSourceLocation());
        $this->emitter->emitNode($node->getAccessExpr());
        $this->emitter->emitStr(')])', $node->getStartSourceLocation());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
