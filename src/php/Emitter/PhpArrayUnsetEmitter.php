<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;
use Phel\Ast\PhpArrayUnsetNode;
use Phel\Emitter;

final class PhpArrayUnsetEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof PhpArrayUnsetNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitStr('unset((', $node->getStartSourceLocation());
        $this->emitter->emit($node->getArrayExpr());
        $this->emitter->emitStr(')[(', $node->getStartSourceLocation());
        $this->emitter->emit($node->getAccessExpr());
        $this->emitter->emitStr(')])', $node->getStartSourceLocation());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
