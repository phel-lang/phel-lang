<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;

final class Emitter implements EmitterInterface
{
    private OutputEmitterInterface $outputEmitter;

    public function __construct(
        OutputEmitterInterface $outputEmitter
    ) {
        $this->outputEmitter = $outputEmitter;
    }

    public function emitNode(AbstractNode $node): string
    {
        return $this->outputEmitter->emitNodeAsString($node);
    }
}
