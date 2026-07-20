<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Gacela;
use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\RunFactory;
use Phel\Shared\ColorStyle;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\Munge;
use Phel\Shared\Printer\Printer;
use Phel\Shared\Printer\PrinterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Shared REPL-command test wiring: a capturing {@see ReplTestIo} and a
 * {@see RunFactory} override that routes REPL output through it.
 *
 * @psalm-require-extends TestCase
 *
 * @phpstan-require-extends TestCase
 */
trait ReplCommandTestTrait
{
    private function createReplTestIo(): ReplTestIo
    {
        $exceptionPrinter = new TextExceptionPrinter(
            new ExceptionArgsPrinter(Printer::readable()),
            ColorStyle::noStyles(),
            new Munge(),
            new FilePositionExtractor(new SourceMapExtractor()),
            $this->createStub(ErrorLogInterface::class),
        );

        return new ReplTestIo($exceptionPrinter);
    }

    private function prepareRunFactory(ReplCommandIoInterface $io): void
    {
        Gacela::overrideExistingResolvedClass(
            RunFactory::class,
            new class($io) extends RunFactory {
                public function __construct(private readonly ReplCommandIoInterface $io) {}

                public function createColorStyle(): ColorStyleInterface
                {
                    return ColorStyle::noStyles();
                }

                public function createPrinter(): PrinterInterface
                {
                    // Match production: RunFactory prints with
                    // Printer::readableWithColor() (strings quoted) since #315fae6e7.
                    // Color is stubbed off above, so the readable, no-color variant
                    // is what the user actually sees. The older nonReadable() here
                    // asserted bare strings the REPL has not emitted for a year, so
                    // `__FILE__` printed nothing instead of `""`.
                    return Printer::readable();
                }

                public function createReplCommandIo(): ReplCommandIoInterface
                {
                    return $this->io;
                }
            },
        );
    }
}
