<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\PhpVarNode;
use Phel\Emitter\NodeEmitter;

final class PhpVarEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpVarNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        if ($node->isCallable()) {
            $this->emitter->emitStr(
                '(function(...$args) { return ' . $node->getName() . '(...$args);' . '})',
                $node->getStartSourceLocation()
            );
        } else {
            $this->emitter->emitStr($node->getName(), $node->getStartSourceLocation());
        }

        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
