<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Gacela;
use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Compiler\Application\Munge;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\RunFactory;
use Phel\Shared\ColorStyle;
use Phel\Shared\ColorStyleInterface;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;

final class ReplBareStdClassTest extends AbstractTestCommand
{
    public function test_repl_resolves_bare_stdclass(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(php/new stdClass)'),
            new InputLine('user:2> ', '(new stdClass)'),
            new InputLine('user:3> ', 'exit'),
        );

        $this->prepareRunFactory($io);

        $exitCode = (new ReplCommand())->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        self::assertSame(0, $exitCode);
        self::assertCount(
            2,
            array_keys($io->getRawOutputs(), 'Printer cannot print this type: stdClass', strict: true),
        );
    }

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
                    return Printer::nonReadable();
                }

                public function createReplCommandIo(): ReplCommandIoInterface
                {
                    return $this->io;
                }
            },
        );
    }
}
