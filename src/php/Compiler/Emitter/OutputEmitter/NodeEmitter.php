<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter;

use Phel\Compiler\Ast\Node;

interface NodeEmitter
{
    public function emit(Node $node): void;
}
