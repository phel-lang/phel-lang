<?php

declare(strict_types=1);

namespace Phel\Run\Domain;

interface StdinReaderInterface
{
    public function read(): string;
}
