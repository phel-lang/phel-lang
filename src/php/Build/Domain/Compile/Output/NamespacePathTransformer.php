<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile\Output;

/**
 * @internal
 */
final class NamespacePathTransformer
{
    public function transform(string $namespace): string
    {
        return strtr(
            $namespace,
            ['\\' => '/', '.' => '/', '-' => '_'],
        );
    }
}
