<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter;

use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;

interface StatementEmitterInterface
{
    public function emitNode(AbstractNode $node, bool $enableSourceMaps): EmitterResult;
}
