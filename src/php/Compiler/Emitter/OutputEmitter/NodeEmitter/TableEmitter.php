<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\Ast\TableNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class TableEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof TableNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('\Phel\Lang\Table::fromKVs(', $node->getStartSourceLocation());
        $this->outputEmitter->emitArgList($node->getKeyValues(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
