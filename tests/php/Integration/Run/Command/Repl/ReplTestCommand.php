<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Generator;
use Override;
use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Infrastructure\SourceMapExtractor;
use Phel\Compiler\Application\Munge;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\Domain\Repl\ColorStyle;
use Phel\Run\Domain\Repl\ColorStyleInterface;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\RunFactory;
use PhelTest\Integration\Run\Command\AbstractTestCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReplTestCommand extends AbstractTestCommand
{
    private string $previousCwd = '';

    public function __construct()
    {
        parent::__construct(self::class);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->previousCwd = getcwd() ?: '';
        chdir(__DIR__);

        GlobalEnvironmentSingleton::reset();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->addAppConfig('config/*.php', 'config/local.php');
        });
    }

    #[Override]
    protected function tearDown(): void
    {
        chdir($this->previousCwd);
    }

    #[DataProvider('providerIntegration')]
    public function test_integration(string $expectedOutput, InputLine ...$inputs): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(...$inputs);
        $this->prepareRunFactory($io);

        $repl = $this->createReplCommand();
        $repl->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        $replOutput = $io->getOutputString();

        self::assertSame(trim($expectedOutput), trim($replOutput));
    }

    /**
     * This is doing the same as the test above except that it will load the core lib before.
     * We split it because it takes some time to load the core lib before every test.
     */
    #[DataProvider('providerIntegrationWithCoreLib')]
    public function test_integration_with_core_lib(string $expectedOutput, InputLine ...$inputs): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(...$inputs);
        $this->prepareRunFactory($io);

        $repl = $this->createReplCommandWithCoreLib();
        $repl->run(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );

        $replOutput = $io->getOutputString();

        self::assertSame(trim($expectedOutput), trim($replOutput));
    }

    public function test_eval_option(): void
    {
        $io = $this->createReplTestIo();
        $this->prepareRunFactory($io);

        $repl = $this->createReplCommand();
        $input = new ArrayInput(['--eval' => '(php/abs -1)']);
        $result = $repl->run(
            $input,
            $this->createStub(OutputInterface::class),
        );

        self::assertSame(Command::SUCCESS, $result);
        self::assertSame(['1'], $io->getRawOutputs());
    }

    public static function providerIntegration(): Generator
    {
        return self::buildDataProviderFromDirectory(realpath(__DIR__ . '/Fixtures'));
    }

    public static function providerIntegrationWithCoreLib(): Generator
    {
        return self::buildDataProviderFromDirectory(realpath(__DIR__ . '/FixturesWithCoreLib'));
    }

    private static function buildDataProviderFromDirectory(string $fixturesDir): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixturesDir),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (preg_match('/\.test$/', $file->getRealPath()) === 0) {
                continue;
            }

            if (preg_match('/\.test$/', $file->getRealPath()) === false) {
                continue;
            }

            $fileContent = file_get_contents($file->getRealpath());
            $filename = str_replace($fixturesDir . '/', '', $file->getRealPath());

            yield $filename => [
                $fileContent,
                ...self::getInputs($fileContent),
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
    private static function getInputs(string $fileContent): array
    {
        $inputs = [];

        foreach (explode(PHP_EOL, $fileContent) as $line) {
            preg_match('/(?<prompt>....:\d> ?)(?<phel_code>.+)?/', $line, $out);
            if ($out !== []) {
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
                public function __construct(private readonly ReplCommandIoInterface $io)
                {
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
            },
        );
    }
}
