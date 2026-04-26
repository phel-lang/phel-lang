<?php

declare(strict_types=1);

namespace Phel\Console\Domain;

use Symfony\Component\Console\Command\Command;

interface ConsoleCommandProviderInterface
{
    /**
     * @return list<Command>
     */
    public function commands(): array;
}
