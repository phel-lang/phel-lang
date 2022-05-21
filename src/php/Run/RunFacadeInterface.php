<?php

declare(strict_types=1);

namespace Phel\Run;

interface RunFacadeInterface
{
    public function runNamespace(string $namespace): void;
}
