<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Ast\Node;
use Phel\Compiler\Emitter\EvalEmitter;
use Phel\Compiler\Emitter\OutputEmitter;

final class Emitter
{
    private OutputEmitter $outputEmitter;

    private EvalEmitter $evalEmitter;

    public static function createWithoutSourceMap(): self
    {
        return new self(
            OutputEmitter::createWithoutSourceMap(),
            new EvalEmitter()
        );
    }

    public static function createWithSourceMap(): self
    {
        return new self(
            OutputEmitter::createWithSourceMap(),
            new EvalEmitter()
        );
    }

    private function __construct(
        OutputEmitter $outputEmitter,
        EvalEmitter $evalEmitter
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
