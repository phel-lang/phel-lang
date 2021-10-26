<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;

interface StatementEmitterInterface
{
    public function emitNode(AbstractNode $node, bool $enableSourceMaps): EmitterResult;
}
