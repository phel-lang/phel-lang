<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\PhpArrayPushNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

final class PhpArrayPushEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpArrayPushNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getArrayExpr());
        $this->outputEmitter->emitStr(')[] = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getValueExpr());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
