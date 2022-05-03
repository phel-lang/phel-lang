<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class VectorEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof VectorNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('\Phel\Lang\TypeFactory::getInstance()->persistentVectorFromArray([', $node->getStartSourceLocation());
        $this->outputEmitter->emitArgList($node->getArgs(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr('])', $node->getStartSourceLocation());
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
