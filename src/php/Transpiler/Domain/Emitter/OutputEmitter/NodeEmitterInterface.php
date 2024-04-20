<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter\OutputEmitter;

use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;

interface NodeEmitterInterface
{
    public function emit(AbstractNode $node): void;
}
