<?php

declare(strict_types=1);

namespace Phel\Emitter;

use Phel\Ast\Node;

interface NodeEmitter
{
    public function emit(Node $node): void;
}
