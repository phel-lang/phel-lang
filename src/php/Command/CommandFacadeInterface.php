<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

interface CommandFacadeInterface
{
    public function writeLocatedException(
        OutputInterface $output,
        AbstractLocatedException $locatedException,
        CodeSnippet $snippet
    ): void;

    public function writeStackTrace(OutputInterface $output, Throwable $e): void;
}
