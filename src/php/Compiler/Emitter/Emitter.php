<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Ast\AbstractNode;

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

    public function emitNodeAndEval(AbstractNode $node): string
    {
        $code = $this->emitNodeAsString($node);
        $this->evalCode($code);

        return $code;
    }

    public function emitNodeAsString(AbstractNode $node): string
    {
        return $this->outputEmitter->emitNodeAsString($node);
    }

    /**
     * @return mixed
     */
    public function evalCode(string $code)
    {
        return $this->evalEmitter->eval($code);
    }
}
