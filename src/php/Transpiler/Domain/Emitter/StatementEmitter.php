<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Emitter;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;

final readonly class StatementEmitter implements StatementEmitterInterface
{
    public function __construct(
        private SourceMapGenerator $sourceMapGenerator,
        private OutputEmitterInterface $outputEmitter,
    ) {
    }

    public function emitNode(AbstractNode $node, bool $enableSourceMaps): EmitterResult
    {
        $this->outputEmitter->resetIndentLevel();
        $this->outputEmitter->resetSourceMapState();

        return new EmitterResult(
            $enableSourceMaps,
            $this->phpCode($node),
            $this->sourceMap($enableSourceMaps),
            $this->source($node),
        );
    }

    private function phpCode(AbstractNode $node): string
    {
        ob_start();
        $this->outputEmitter->emitNode($node);

        return ob_get_clean();
    }

    private function sourceMap(bool $enableSourceMaps): string
    {
        if (!$enableSourceMaps) {
            return '';
        }

        return $this->sourceMapGenerator->encode(
            $this->outputEmitter->getSourceMapState()->getMappings(),
        );
    }

    private function source(AbstractNode $node): string
    {
        $sourceLocation = $node->getStartSourceLocation();

        return ($sourceLocation instanceof SourceLocation)
            ? $sourceLocation->getFile()
            : 'string';
    }
}
