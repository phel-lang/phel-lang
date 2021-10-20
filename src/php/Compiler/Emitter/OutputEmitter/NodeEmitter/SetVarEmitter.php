<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class SetVarEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof SetVarNode);

        $this->outputEmitter->emitNode($node->getSymbol());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getValueExpr());
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
    }
}
