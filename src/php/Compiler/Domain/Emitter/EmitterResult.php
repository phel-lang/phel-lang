<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Shared\SourceMap\InlineSourceMapComments;

/**
 * @phpstan-import-type DeprecationRecord from DeprecationWarnings
 *
 * @internal
 */
final readonly class EmitterResult
{
    /**
     * @param list<DeprecationRecord> $deprecations the notices this compile found, raised or not (#3222)
     */
    public function __construct(
        private bool $enableSourceMaps,
        private string $phpCode,
        private string $sourceMap,
        private string $source,
        private array $deprecations = [],
    ) {}

    /**
     * @param list<DeprecationRecord> $deprecations
     */
    public function withDeprecations(array $deprecations): self
    {
        return new self($this->enableSourceMaps, $this->phpCode, $this->sourceMap, $this->source, $deprecations);
    }

    /**
     * The deprecation notices found while compiling this source, whether or
     * not the flag let them be raised, so a cache can replay them on a hit.
     *
     * @return list<DeprecationRecord>
     */
    public function getDeprecations(): array
    {
        return $this->deprecations;
    }

    public function getPhpCode(): string
    {
        return $this->phpCode;
    }

    public function getSourceMap(): string
    {
        return $this->sourceMap;
    }

    public function getCodeWithSourceMap(): string
    {
        if ($this->enableSourceMaps) {
            return (
                InlineSourceMapComments::FILENAME_PREFIX . $this->source . "\n"
                . InlineSourceMapComments::MAPPINGS_PREFIX . $this->sourceMap . "\n"
                . $this->phpCode
            );
        }

        return $this->phpCode;
    }
}
