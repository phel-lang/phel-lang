<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\Node;
use Phel\Ast\TableNode;
use Phel\Emitter\NodeEmitter;

final class TableEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof TableNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->emitter->emitStr('\Phel\Lang\Table::fromKVs(', $node->getStartSourceLocation());
        $this->emitter->emitArgList($node->getKeyValues(), $node->getStartSourceLocation());
        $this->emitter->emitStr(')', $node->getStartSourceLocation());
        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
