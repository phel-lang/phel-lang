<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Builder\Output;

final class NamespacePathTransformer
{
    public function transform(string $namespace): string
    {
        return str_replace(
            ['\\', '-'],
            ['/', '_'],
            $namespace,
        );
    }
}
