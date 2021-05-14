<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;

interface EmitterInterface
{
    public function emitNode(AbstractNode $node): string;
}
