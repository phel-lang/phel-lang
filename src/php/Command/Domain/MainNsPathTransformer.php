<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

final class MainNsPathTransformer
{
    public function __construct(
        private string $outputMainNs,
    ) {
    }

    public function getOutputMainNsPath(): string
    {
        return str_replace(
            ['\\', '-'],
            ['/', '_'],
            $this->outputMainNs,
        );
    }
}
