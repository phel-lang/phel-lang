<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Gacela;
use Generator;
use Phel\Command\Domain\Shared\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Shared\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Domain\Shared\Exceptions\Extractor\SourceMapExtractor;
use Phel\Command\Domain\Shared\Exceptions\TextExceptionPrinter;
use Phel\Compiler\Infrastructure\Munge;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\ColorStyle;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\RunFactory;
use PhelTest\Integration\Run\Command\AbstractCommandTest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReplCommandTest extends AbstractCommandTest
{
    public function setUp(): void
    {
        $configFn = static function (GacelaConfig $config): void {
            $config->addAppConfig('config/*.php', 'config/local.php');
            $config->setCacheEnabled(false);
        };
        Gacela::bootstrap(__DIR__, $configFn);
    }

    /**
     * @dataProvider providerIntegration
     */
    public function test_integration(string $expectedOutput, InputLine ...$inputs): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(...$inputs);
        $this->prepareRunDependencyProvider($io);

        $repl = $this->createReplCommand();
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
        $this->prepareRunDependencyProvider($io);

        $repl = $this->createReplCommandWithCoreLib();
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

    private function createReplCommand(): ReplCommand
    {
        return new ReplCommand();
    }

    private function createReplCommandWithCoreLib(): ReplCommand
    {
        $replStartupFile = __DIR__ . '/../../../../../../src/php/Run/Domain/Repl/startup.phel';

        return (new ReplCommand())->setReplStartupFile($replStartupFile);
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

    private function prepareRunDependencyProvider(ReplCommandIoInterface $io): void
    {
        AnonymousGlobal::overrideExistingResolvedClass(
            RunFactory::class,
            new class($io) extends RunFactory {
                private ReplCommandIoInterface $io;

                public function __construct(ReplCommandIoInterface $io)
                {
                    $this->io = $io;
                }

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
            }
        );
    }
}
