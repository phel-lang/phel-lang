<?php

declare(strict_types=1);

namespace Phel\Run\Domain;

/**
 * @internal
 */
interface StdinReaderInterface
{
    public function read(): string;
}
