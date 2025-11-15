<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command;

use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Filesystem\Infrastructure\RealFilesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractTestCommand extends TestCase
{
    protected function setUp(): void
    {
        GlobalEnvironmentSingleton::initializeNew();
        RealFilesystem::reset();
    }

    protected function stubOutput(): OutputInterface
    {
        return new class() implements OutputInterface {
            public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
            {
                echo is_iterable($messages) ? implode('', $messages) : $messages;
                if ($newline) {
                    echo PHP_EOL;
                }
            }

            public function writeln(iterable|string $messages, int $options = 0): void
            {
                $this->write($messages, true, $options);
            }

            public function setVerbosity(int $level): void
            {
            }

            public function getVerbosity(): int
            {
                return OutputInterface::VERBOSITY_NORMAL;
            }

            public function isQuiet(): bool
            {
                return false;
            }

            public function isVerbose(): bool
            {
                return false;
            }

            public function isVeryVerbose(): bool
            {
                return false;
            }

            public function isDebug(): bool
            {
                return false;
            }

            public function setDecorated(bool $decorated): void
            {
            }

            public function isDecorated(): bool
            {
                return false;
            }

            public function setFormatter(OutputFormatterInterface $formatter): void
            {
            }

            public function getFormatter(): OutputFormatterInterface
            {
                return new OutputFormatter();
            }
        };
    }
}
