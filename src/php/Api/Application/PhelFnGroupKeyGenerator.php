<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\PhelFnGroupKeyGeneratorInterface;

final readonly class PhelFnGroupKeyGenerator implements PhelFnGroupKeyGeneratorInterface
{
    public function generateGroupKey(string $namespace, string $name): string
    {
        $key = preg_replace(
            '/[^a-zA-Z0-9\-]+/',
            '',
            str_replace('/', '-', $name),
        );

        $lower = strtolower(rtrim((string) $key, '-'));

        return ltrim($lower, '/');
    }
}
