<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFactory;
use Phel\Command\Finder\ComposerVendorDirectoriesFinder;
use Phel\Command\Finder\DirectoryFinder;
use Phel\Command\Finder\DirectoryFinderInterface;
use Phel\Command\Finder\VendorDirectoriesFinderInterface;
use Phel\Command\Shared\CommandExceptionWriter;
use Phel\Command\Shared\CommandExceptionWriterInterface;
use Phel\Command\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Command\Shared\Exceptions\TextExceptionPrinter;

/**
 * @method CommandConfig getConfig()
 */
final class CommandFactory extends AbstractFactory
{
    public function createCommandExceptionWriter(): CommandExceptionWriterInterface
    {
        return new CommandExceptionWriter(
            $this->createExceptionPrinter()
        );
    }

    public function createExceptionPrinter(): ExceptionPrinterInterface
    {
        return TextExceptionPrinter::create();
    }

    public function createDirectoryFinder(): DirectoryFinderInterface
    {
        return new DirectoryFinder(
            $this->getConfig()->getApplicationRootDir(),
            $this->getConfig()->getConfigDirectories(),
            $this->createComposerVendorDirectoriesFinder()
        );
    }

    private function createComposerVendorDirectoriesFinder(): VendorDirectoriesFinderInterface
    {
        return new ComposerVendorDirectoriesFinder(
            $this->getConfig()->getVendorDir()
        );
    }
}
