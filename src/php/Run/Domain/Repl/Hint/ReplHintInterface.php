<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl\Hint;

use Throwable;

interface ReplHintInterface
{
    public function appliesTo(Throwable $e): bool;

    public function hint(Throwable $e): string;
}
