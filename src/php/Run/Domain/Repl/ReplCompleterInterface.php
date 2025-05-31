<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Repl;

interface ReplCompleterInterface
{
    /**
     * @return list<string>
     */
    public function complete(string $input): array;
}
