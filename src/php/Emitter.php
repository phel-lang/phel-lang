<?php

declare(strict_types=1);

namespace Phel;

use Phel\Ast\Node;
use Phel\Emitter\EvalEmitter;
use Phel\Emitter\NodeEmitterFactory;
use Phel\Emitter\OutputEmitter;
use Phel\SourceMap\SourceMapGenerator;

final class Emitter
{
    private OutputEmitter $outputEmitter;

    private EvalEmitter $evalEmitter;

    public static function createWithoutSourceMap(): self
    {
        return new self(
            new OutputEmitter(
                $enableSourceMaps = false,
                new SourceMapGenerator(),
                new NodeEmitterFactory()
            ),
            new EvalEmitter()
        );
    }

    public static function createWithSourceMap(): self
    {
        return new self(
            new OutputEmitter(
                $enableSourceMaps = true,
                new SourceMapGenerator(),
                new NodeEmitterFactory()
            ),
            new EvalEmitter()
        );
    }

    private function __construct(OutputEmitter $outputEmitter, EvalEmitter $evalEmitter)
    {
        $this->outputEmitter = $outputEmitter;
        $this->evalEmitter = $evalEmitter;
    }

    public function emitNodeAndEval(Node $node): string
    {
        $code = $this->emitNodeAsString($node);
        $this->evalEmitter->eval($code);

        return $code;
    }

    public function emitNodeAsString(Node $node): string
    {
        return $this->outputEmitter->emitNodeAsString($node);
    }
}
