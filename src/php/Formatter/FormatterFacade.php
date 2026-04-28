<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\Framework\AbstractFacade;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Shared\Facade\FormatterFacadeInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @extends AbstractFacade<FormatterFactory>
 */
final class FormatterFacade extends AbstractFacade implements FormatterFacadeInterface
{
    /**
     * @return list<string> paths whose contents changed (or would change under $dryRun)
     */
    public function format(array $paths, OutputInterface $output, bool $dryRun = false): array
    {
        return $this->getFactory()
            ->createPathsFormatter()
            ->format($paths, $output, $dryRun);
    }

    /**
     * Format a Phel source string in memory without touching the filesystem.
     */
    public function formatString(string $source, string $uri = FormatterInterface::DEFAULT_SOURCE): string
    {
        return $this->getFactory()
            ->createFormatter()
            ->format($source, $uri);
    }
}
