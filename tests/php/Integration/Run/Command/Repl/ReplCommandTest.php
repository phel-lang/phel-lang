<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Gacela;
use Generator;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Command\Shared\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Shared\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Shared\Exceptions\Extractor\SourceMapExtractor;
use Phel\Command\Shared\Exceptions\TextExceptionPrinter;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Printer\Printer;
use Phel\Run\Command\ReplCommand;
use Phel\Run\Domain\Repl\ColorStyle;
use PhelTest\Integration\Run\Command\AbstractCommandTest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReplCommandTest extends AbstractCommandTest
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    /**
     * @dataProvider providerIntegration
     */
    public function test_integration(string $expectedOutput, InputLine ...$inputs): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(...$inputs);

        $repl = $this->createReplCommand($io);
        $repl->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class)
        );

        $replOutput = $io->getOutputString();

        self::assertEquals(trim($expectedOutput), trim($replOutput));
    }

    /**
     * This is doing the same as the test above except that it will load the core lib before.
     * We split it because it takes some time to load the core lib before every test.
     *
     * @dataProvider providerIntegrationWithCoreLib
     */
    public function test_integration_with_core_lib(string $expectedOutput, InputLine ...$inputs): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(...$inputs);

        $repl = $this->createReplCommandWithCoreLib($io);
        $repl->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class)
        );

        $replOutput = $io->getOutputString();

        self::assertEquals(trim($expectedOutput), trim($replOutput));
    }

    public function providerIntegration(): Generator
    {
        return $this->buildDataProviderFromDirectory(realpath(__DIR__ . '/Fixtures'));
    }

    public function providerIntegrationWithCoreLib(): Generator
    {
        return $this->buildDataProviderFromDirectory(realpath(__DIR__ . '/FixturesWithCoreLib'));
    }

    private function buildDataProviderFromDirectory(string $fixturesDir): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixturesDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!preg_match('/\.test$/', $file->getRealPath())) {
                continue;
            }

            $fileContent = file_get_contents($file->getRealpath());
            $filename = str_replace($fixturesDir . '/', '', $file->getRealPath());

            yield $filename => [
                $fileContent,
                ...$this->getInputs($fileContent),
            ];
        }
    }

    private function createReplCommand(ReplTestIo $io): ReplCommand
    {
        return new ReplCommand(
            $io,
            new CompilerFacade(),
            ColorStyle::noStyles(),
            Printer::nonReadable(),
            new BuildFacade(),
            new CommandFacade()
        );
    }

    private function createReplCommandWithCoreLib(ReplTestIo $io): ReplCommand
    {
        $replStartupFile = __DIR__ . '/../../../../../../src/php/Run/Domain/Repl/startup.phel';

        return new ReplCommand(
            $io,
            new CompilerFacade(),
            ColorStyle::noStyles(),
            Printer::nonReadable(),
            new BuildFacade(),
            new CommandFacade(),
            $replStartupFile
        );
    }

    /**
     * @return InputLine[]
     */
    private function getInputs(string $fileContent): array
    {
        $inputs = [];

        foreach (explode(PHP_EOL, $fileContent) as $line) {
            preg_match('/(?<prompt>....:\d> ?)(?<phel_code>.+)?/', $line, $out);
            if (!empty($out)) {
                $prompt = $out['prompt'];
                $code = $out['phel_code'] ?? '';
                $inputs[] = new InputLine($prompt, $code);
            }
        }

        return $inputs;
    }

    private function createReplTestIo(): ReplTestIo
    {
        $exceptionPrinter = new TextExceptionPrinter(
            new ExceptionArgsPrinter(Printer::readable()),
            ColorStyle::noStyles(),
            new Munge(),
            new FilePositionExtractor(new SourceMapExtractor())
        );

        return new ReplTestIo($exceptionPrinter);
    }
}
