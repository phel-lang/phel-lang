<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class PhpArrayGetEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpArrayGetNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('((', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getArrayExpr());
        foreach ($node->getAccessExprs() as $i => $accessExpr) {
            $this->outputEmitter->emitStr($i === 0 ? ')[(' : '[(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($accessExpr);
            $this->outputEmitter->emitStr(')]', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitStr(' ?? null)', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
