<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;
use Phel\Ast\PhpVarNode;
use Phel\Emitter;

final class PhpVarEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

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
