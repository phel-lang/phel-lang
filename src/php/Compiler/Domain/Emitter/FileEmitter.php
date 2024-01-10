<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;

final class FileEmitter implements FileEmitterInterface
{
    private string $phpCode = '';

    private string $source = '';

    public function __construct(
        private readonly SourceMapGenerator $sourceMapGenerator,
        private readonly OutputEmitterInterface $outputEmitter,
    ) {
    }

    public function startFile(string $source): void
    {
        $this->outputEmitter->resetIndentLevel();
        $this->outputEmitter->resetSourceMapState();

        $this->source = $source;
        $this->phpCode = '';
    }

    public function emitNode(AbstractNode $node): void
    {
        ob_start();
        $this->outputEmitter->emitNode($node);
        $this->phpCode .= ob_get_clean();
    }

    public function endFile(bool $enableSourceMaps): EmitterResult
    {
        $sourceMap = $this->sourceMapGenerator->encode(
            $this->outputEmitter->getSourceMapState()->getMappings(),
        );

        return new EmitterResult(
            $enableSourceMaps,
            $this->phpCode,
            $sourceMap,
            $this->source,
        );
    }
}
