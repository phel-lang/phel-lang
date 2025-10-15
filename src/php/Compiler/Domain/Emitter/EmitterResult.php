<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

final readonly class EmitterResult
{
    public function __construct(
        private bool $enableSourceMaps,
        private string $phpCode,
        private string $sourceMap,
        private string $sourceFilePath,
    ) {
    }

    public function getPhpCode(): string
    {
        return $this->phpCode;
    }

    public function getSourceMap(): string
    {
        return $this->sourceMap;
    }

    public function getSourceFilePath(): string
    {
        return $this->sourceFilePath;
    }

    public function getCodeWithSourceMap(): string
    {
        if ($this->enableSourceMaps) {
            return (
                '// ' . $this->sourceFilePath . "\n"
                . '// ;;' . $this->sourceMap . "\n"
                . $this->phpCode
            );
        }

        return $this->phpCode;
    }
}
