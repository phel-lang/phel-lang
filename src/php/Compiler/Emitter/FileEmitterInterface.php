<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;

interface FileEmitterInterface
{
    public function startFile(string $source): void;

    public function emitNode(AbstractNode $node): void;

    public function endFile(bool $enableSourceMaps): EmitterResult;
}
