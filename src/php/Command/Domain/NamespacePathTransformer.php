<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

final class NamespacePathTransformer
{
    public function __construct(
        private string $outputMainNamespace,
    ) {
    }

    public function getOutputMainNamespacePath(): string
    {
        return str_replace(
            ['\\', '-'],
            ['/', '_'],
            $this->outputMainNamespace,
        );
    }
}
