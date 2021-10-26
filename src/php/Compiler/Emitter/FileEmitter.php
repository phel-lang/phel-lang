<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;

final class FileEmitter implements FileEmitterInterface
{
    private SourceMapGenerator $sourceMapGenerator;
    private OutputEmitterInterface $outputEmitter;
    private string $code = '';
    private string $source = '';

    public function __construct(
        SourceMapGenerator $sourceMapGenerator,
        OutputEmitterInterface $outputEmitter
    ) {
        $this->sourceMapGenerator = $sourceMapGenerator;
        $this->outputEmitter = $outputEmitter;
    }

    public function startFile(string $source): void
    {
        $this->outputEmitter->resetIndentLevel();
        $this->outputEmitter->resetSourceMapState();
        $this->source = $source;
        $this->code = '';
    }

    public function emitNode(AbstractNode $node): void
    {
        ob_start();
        $this->outputEmitter->emitNode($node);
        $this->code .= ob_get_clean();
    }

    public function endFile(bool $enableSourceMaps): EmitterResult
    {
        $sourceMap = $this->sourceMapGenerator->encode(
            $this->outputEmitter->getSourceMapState()->getMappings()
        );

        return new EmitterResult(
            $enableSourceMaps,
            $this->code,
            $sourceMap,
            $this->source
        );
    }
}
