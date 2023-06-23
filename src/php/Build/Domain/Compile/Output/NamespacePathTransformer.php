<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile\Output;

final class NamespacePathTransformer
{
    public function getOutputMainNamespacePath(string $outputMainNamespace): string
    {
        return str_replace(
            ['\\', '-'],
            ['/', '_'],
            $outputMainNamespace,
        );
    }
}
