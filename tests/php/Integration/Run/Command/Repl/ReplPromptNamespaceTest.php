<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Gacela;
use Override;
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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\Console\Input\InputInterface;

final class ReplPromptNamespaceTest extends AbstractTestCommand
{
    private string $previousCwd = '';

    private string $tempDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousCwd = getcwd() ?: '';
        $this->tempDir = $this->containerTempDir();
        chdir($this->tempDir);
        Gacela::bootstrap($this->tempDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        $this->cleanupContainerTempDirs();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_prompt_starts_with_user_namespace(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(php/+ 1 1)'),
            new InputLine('user:2> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        $output = $io->getOutputString();
        self::assertStringContainsString('user:1> ', $output);
        self::assertStringContainsString('user:2> ', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_prompt_switches_after_ns_form(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(ns my-app)'),
            new InputLine('my-app:2> ', '(php/+ 2 3)'),
            new InputLine('my-app:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        $output = $io->getOutputString();
        self::assertStringContainsString('user:1> (ns my-app)', $output);
        self::assertStringContainsString('my-app:2> (php/+ 2 3)', $output);
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
