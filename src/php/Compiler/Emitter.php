<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Ast\Node;
use Phel\Compiler\Emitter\EvalEmitterInterface;
use Phel\Compiler\Emitter\OutputEmitterInterface;

final class Emitter implements EmitterInterface
{
    private OutputEmitterInterface $outputEmitter;
    private EvalEmitterInterface $evalEmitter;

    public function __construct(
        OutputEmitterInterface $outputEmitter,
        EvalEmitterInterface $evalEmitter
    ) {
        $this->outputEmitter = $outputEmitter;
        $this->evalEmitter = $evalEmitter;
    }

    public function emitNodeAndEval(Node $node): string
    {
        $code = $this->emitNodeAsString($node);
        $this->evalCode($code);

        return $code;
    }

    public function emitNodeAsString(Node $node): string
    {
        return $this->outputEmitter->emitNodeAsString($node);
    }

    /** @return mixed */
    public function evalCode(string $code)
    {
        return $this->evalEmitter->eval($code);
    }
}
