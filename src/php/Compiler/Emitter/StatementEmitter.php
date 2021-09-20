<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;

final class StatementEmitter implements StatementEmitterInterface
{
    private bool $enableSourceMaps;
    private SourceMapGenerator $sourceMapGenerator;
    private OutputEmitterInterface $outputEmitter;

    public function __construct(
        bool $enableSourceMaps,
        SourceMapGenerator $sourceMapGenerator,
        OutputEmitterInterface $outputEmitter
    ) {
        $this->enableSourceMaps = $enableSourceMaps;
        $this->sourceMapGenerator = $sourceMapGenerator;
        $this->outputEmitter = $outputEmitter;
    }

    public function emitNode(AbstractNode $node): EmitterResult
    {
        $this->outputEmitter->resetIndentLevel();
        $this->outputEmitter->resetSourceMapState();

        $sourceLocation = $node->getStartSourceLocation();
        $file = $sourceLocation
            ? $sourceLocation->getFile()
            : 'string';

        ob_start();
        $this->outputEmitter->emitNode($node);
        $code = ob_get_clean();

        if (!$this->enableSourceMaps) {
            return new EmitterResult(
                $this->enableSourceMaps,
                $code,
                '',
                $file
            );
        }

        $sourceMap = $this->sourceMapGenerator->encode(
            $this->outputEmitter->getSourceMapState()->getMappings()
        );

        return new EmitterResult(
            $this->enableSourceMaps,
            $code,
            $sourceMap,
            $file
        );
    }
}
