<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Exceptions\CompiledCodeIsMalformedException;
use Phel\Exceptions\FileException;

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

    public function emitNodeAsString(AbstractNode $node): string
    {
        return $this->outputEmitter->emitNodeAsString($node);
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function emitNodeAndEval(AbstractNode $node): string
    {
        $code = $this->emitNodeAsString($node);
        $this->evalCode($code);

        return $code;
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return mixed
     */
    public function evalCode(string $code)
    {
        return $this->evalEmitter->eval($code);
    }
}
