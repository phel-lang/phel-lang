<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;
use function count;

final class PhpArrayUnsetEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpArrayUnsetNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('unset((', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getArrayExpr());

        $accessExprs = $node->getAccessExprs();
        foreach ($accessExprs as $i => $accessExpr) {
            $isLast = $i === count($accessExprs) - 1;
            $this->outputEmitter->emitStr($i === 0 ? ')[(' : '[(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($accessExpr);
            $this->outputEmitter->emitStr($isLast ? ')])' : ')]', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
