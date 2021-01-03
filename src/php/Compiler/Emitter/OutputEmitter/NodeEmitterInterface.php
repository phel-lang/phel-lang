<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;

interface NodeEmitterInterface
{
    public function emit(AbstractNode $node): void;
}
