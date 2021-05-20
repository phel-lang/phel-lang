<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;

final class Emitter implements EmitterInterface
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

    public function emitNode(AbstractNode $node): string
    {
        $this->outputEmitter->resetIndentLevel();
        $this->outputEmitter->resetSourceMapState();

        ob_start();
        $this->outputEmitter->emitNode($node);
        $code = ob_get_contents();
        ob_end_clean();

        if (!$this->enableSourceMaps) {
            return $code;
        }

        $sourceMap = $this->sourceMapGenerator->encode(
            $this->outputEmitter->getSourceMapState()->getMappings()
        );
        $sourceLocation = $node->getStartSourceLocation();
        $file = $sourceLocation
            ? $sourceLocation->getFile()
            : 'string';

        return (
            '// ' . $file . "\n"
            . '// ;;' . $sourceMap . "\n"
            . $code
        );
    }
}
