<?php

declare(strict_types=1);

namespace Phel\Shared\Parser\ReadModel;

use Phel\Lang\SourceLocation;

final readonly class CodeSnippet
{
    public function __construct(
        private SourceLocation $startLocation,
        private SourceLocation $endLocation,
        private string $code,
    ) {}

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
