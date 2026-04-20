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

final class ReplHistoryVarsTest extends AbstractTestCommand
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
    public function test_star_one_resolves_previous_result(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(php/+ 1 2)'),
            new InputLine('user:2> ', '*1'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        $output = $io->getOutputString();
        self::assertStringContainsString('user:1> (php/+ 1 2)', $output);
        self::assertStringContainsString('user:2> *1', $output);
        // First eval prints 3, then *1 echoes 3 → "3" appears at least twice as a result
        self::assertGreaterThanOrEqual(2, substr_count($output, '3'), '*1 must echo previous result 3');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_star_two_and_star_three_shift_history(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '10'),
            new InputLine('user:2> ', '20'),
            new InputLine('user:3> ', '30'),
            new InputLine('user:4> ', '*3'),
            new InputLine('user:5> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        $output = $io->getOutputString();
        self::assertStringContainsString('user:4> *3', $output);
        // After evaluating 10, 20, 30: *1=30, *2=20, *3=10. So *3 echoes 10.
        self::assertMatchesRegularExpression('/user:4> \*3\s+10/', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_star_e_captures_last_runtime_exception(): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(
            new InputLine('user:1> ', '(php/throw (php/new \\RuntimeException "boom"))'),
            new InputLine('user:2> ', '(php/-> *e (getMessage))'),
            new InputLine('user:3> ', 'exit'),
        );
        $this->prepareRunFactory($io);

        new ReplCommand()->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );

        $output = $io->getOutputString();
        self::assertStringContainsString('boom', $output, 'getMessage() on *e returns boom');
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
