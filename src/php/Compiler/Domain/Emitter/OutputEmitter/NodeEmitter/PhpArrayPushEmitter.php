<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class PhpArrayPushEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpArrayPushNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getArrayExpr());

        $accessExprs = $node->getAccessExprs();
        if ($accessExprs === []) {
            $this->outputEmitter->emitStr(')[] = ', $node->getStartSourceLocation());
        } else {
            foreach ($accessExprs as $i => $accessExpr) {
                $this->outputEmitter->emitStr($i === 0 ? ')[(' : '[(', $node->getStartSourceLocation());
                $this->outputEmitter->emitNode($accessExpr);
                $this->outputEmitter->emitStr(')]', $node->getStartSourceLocation());
            }

            $this->outputEmitter->emitStr('[] = ', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitNode($node->getValueExpr());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
