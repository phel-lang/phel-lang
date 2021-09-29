<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractFactory;
use Phel\Command\Shared\CommandExceptionWriter;
use Phel\Command\Shared\CommandExceptionWriterInterface;
use Phel\Command\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Command\Shared\Exceptions\TextExceptionPrinter;

final class CommandFactory extends AbstractFactory
{
    public function createCommandExceptionWriter(): CommandExceptionWriterInterface
    {
        return new CommandExceptionWriter(
            $this->createExceptionPrinter()
        );
    }

    private function createExceptionPrinter(): ExceptionPrinterInterface
    {
        return TextExceptionPrinter::create();
    }
}
