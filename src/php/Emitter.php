<?php

declare(strict_types=1);

namespace Phel;

use Phel\Ast\Node;
use Phel\Emitter\OutputEmitter;
use Phel\Emitter\EvalEmitter;
use Phel\SourceMap\SourceMapGenerator;

final class Emitter
{
    private OutputEmitter $outputEmitter;

    private EvalEmitter $evalEmitter;

    public function __construct(bool $enableSourceMaps = true)
    {
        $this->outputEmitter = new OutputEmitter(
            $enableSourceMaps,
            new SourceMapGenerator(),
        );

        $this->evalEmitter = new EvalEmitter();
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
