<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Shared\SourceMap\InlineSourceMapComments;

final readonly class EmitterResult
{
    public function __construct(
        private bool $enableSourceMaps,
        private string $phpCode,
        private string $sourceMap,
        private string $source,
    ) {}

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
