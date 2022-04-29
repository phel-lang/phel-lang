<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;

interface NodeEmitterInterface
{
    public function emit(AbstractNode $node): void;
}
