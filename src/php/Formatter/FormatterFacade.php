<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\Framework\AbstractFacade;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method FormatterFactory getFactory()
 */
final class FormatterFacade extends AbstractFacade implements FormatterFacadeInterface
{
    /**
     * @return list<string> successful formatted file paths
     */
    public function format(array $paths, OutputInterface $output): array
    {
        return $this->getFactory()
            ->createPathsFormatter()
            ->format($paths, $output);
    }
}
