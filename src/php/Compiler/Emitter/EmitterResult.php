<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

final class EmitterResult
{
    private bool $enableSourceMaps;
    private string $code;
    private string $sourceMap;
    private string $source;

    public function __construct(bool $enableSourceMaps, string $code, string $sourceMap, string $source)
    {
        $this->enableSourceMaps = $enableSourceMaps;
        $this->code = $code;
        $this->sourceMap = $sourceMap;
        $this->source = $source;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getSourceMap(): string
    {
        return $this->sourceMap;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getCodeWithSourceMap(): string
    {
        if ($this->enableSourceMaps) {
            return (
                '// ' . $this->source . "\n"
                . '// ;;' . $this->sourceMap . "\n"
                . $this->code
            );
        }

        return $this->code;
    }
}
