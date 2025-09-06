<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

interface PhelFnGroupKeyGeneratorInterface
{
    public function generateGroupKey(string $namespace, string $name): string;
}
