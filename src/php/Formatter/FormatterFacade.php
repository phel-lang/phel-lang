<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Facade\FormatterFacadeInterface;
use Phel\Shared\Formatter\FormatResult;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @extends AbstractFacade<FormatterFactory>
 */
#[ServiceMap(method: 'getFactory', className: FormatterFactory::class)]
final class FormatterFacade extends AbstractFacade implements FormatterFacadeInterface
{
    /**
     * @param list<string> $paths
     * @param list<string> $exclude
     */
    public function format(array $paths, OutputInterface $output, bool $dryRun = false, array $exclude = []): FormatResult
    {
        return $this->getFactory()
            ->createPathsFormatter()
            ->format($paths, $output, $dryRun, $exclude);
    }

    /**
     * Format a Phel source string in memory without touching the filesystem.
     */
    public function formatString(string $source, string $uri = CompilerConstants::DEFAULT_SOURCE): string
    {
        return $this->getFactory()
            ->createFormatter()
            ->format($source, $uri);
    }
}
