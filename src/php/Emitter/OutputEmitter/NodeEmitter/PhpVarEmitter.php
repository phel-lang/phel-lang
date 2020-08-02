<?php

declare(strict_types=1);

namespace Phel\Emitter\OutputEmitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\PhpVarNode;
use Phel\Emitter\OutputEmitter\NodeEmitter;

final class PhpVarEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof PhpVarNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        if ($node->isCallable()) {
            $this->outputEmitter->emitStr(
                '(function(...$args) { return ' . $node->getName() . '(...$args);' . '})',
                $node->getStartSourceLocation()
            );
        } else {
            $this->outputEmitter->emitStr($node->getName(), $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
