<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

interface ReplCompleterInterface
{
    /**
     * @return list<string>
     */
    public function complete(string $input): array;
}
