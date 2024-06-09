<?php

declare(strict_types=1);

namespace Phel\Command\Domain;

interface ErrorLogInterface
{
    public function writeln(string $text): void;
}
