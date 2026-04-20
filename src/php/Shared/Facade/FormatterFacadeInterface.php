<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Symfony\Component\Console\Output\OutputInterface;

interface FormatterFacadeInterface
{
    /**
     * @return list<string> paths whose contents changed (or would change under $dryRun)
     */
    public function format(array $paths, OutputInterface $output, bool $dryRun = false): array;
}
