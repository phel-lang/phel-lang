<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;

interface StatementEmitterInterface
{
    public function emitNode(AbstractNode $node, bool $enableSourceMaps): EmitterResult;
}
