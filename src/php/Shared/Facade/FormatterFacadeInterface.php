<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Symfony\Component\Console\Output\OutputInterface;

interface FormatterFacadeInterface
{
    public function format(array $paths, OutputInterface $output): array;
}
