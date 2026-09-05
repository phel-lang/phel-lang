<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use RuntimeException;

use function array_slice;
use function count;
use function strlen;
use function substr;

/**
 * @internal
 */
final class FileEmitter implements FileEmitterInterface
{
    private string $phpCode = '';

    private string $source = '';

    /** Where in `$phpCode` the most recent `emitNode` started writing. */
    private int $lastEmittedOffset = 0;

    public function __construct(
        private readonly SourceMapGenerator $sourceMapGenerator,
        private readonly OutputEmitterInterface $outputEmitter,
    ) {}

    public function startFile(string $source): void
    {
        $this->outputEmitter->resetIndentLevel();
        $this->outputEmitter->resetSourceMapState();
        $this->outputEmitter->resetPhpNamespaceDeclaration();

        $this->source = $source;
        $this->phpCode = '';
        $this->lastEmittedOffset = 0;
    }

    public function emitNode(AbstractNode $node): void
    {
        $this->lastEmittedOffset = strlen($this->phpCode);

        ob_start();
        $this->outputEmitter->emitNode($node);
        $buffer = ob_get_clean();

        if ($buffer === false) {
            throw new RuntimeException('Unable to capture emitted PHP code.');
        }

        $this->phpCode .= $buffer;
    }

    public function emitNodeCapturing(AbstractNode $node, bool $enableSourceMaps): ?EmitterResult
    {
        $state = $this->outputEmitter->getSourceMapState();
        $mappingsBefore = count($state->getMappings());
        $lineBefore = $state->getGeneratedLines();

        $this->outputEmitter->getOptions()->resetDivergenceRecord();
        $this->emitNode($node);

        if ($this->outputEmitter->getOptions()->hasDivergedFromStatementMode()) {
            return null;
        }

        return new EmitterResult(
            $enableSourceMaps,
            substr($this->phpCode, $this->lastEmittedOffset),
            $enableSourceMaps ? $this->encodeMappingsSince($mappingsBefore, $lineBefore) : '',
            $this->source,
        );
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

    /**
     * The mappings this form added, rebased so its first generated line is 0.
     *
     * Only lines are rebased. Every top-level emission ends on `emitLine`, so a
     * form always starts at column 0 of a fresh line, which is exactly where a
     * statement emission's own reset puts it.
     */
    private function encodeMappingsSince(int $mappingsBefore, int $lineBefore): string
    {
        $mappings = array_slice($this->outputEmitter->getSourceMapState()->getMappings(), $mappingsBefore);

        foreach ($mappings as $index => $mapping) {
            $mapping['generated']['line'] -= $lineBefore;
            $mappings[$index] = $mapping;
        }

        return $this->sourceMapGenerator->encode($mappings);
    }
}
