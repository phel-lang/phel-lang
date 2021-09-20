<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;

final class FileEmitter implements FileEmitterInterface
{
    private bool $enableSourceMaps;
    private SourceMapGenerator $sourceMapGenerator;
    private OutputEmitterInterface $outputEmitter;
    private string $code = '';
    private string $source = '';

    public function __construct(
        bool $enableSourceMaps,
        SourceMapGenerator $sourceMapGenerator,
        OutputEmitterInterface $outputEmitter
    ) {
        $this->enableSourceMaps = $enableSourceMaps;
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

    public function endFile(): EmitterResult
    {
        $sourceMap = $this->sourceMapGenerator->encode(
            $this->outputEmitter->getSourceMapState()->getMappings()
        );

        return new EmitterResult(
            $this->enableSourceMaps,
            $this->code,
            $sourceMap,
            $this->source
        );
    }
}
