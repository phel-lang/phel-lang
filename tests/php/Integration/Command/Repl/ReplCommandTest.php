<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Repl;

use Generator;
use Phel\Command\Repl\ColorStyle;
use Phel\Command\Repl\ReplCommand;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Printer\Printer;
use Phel\Runtime\Exceptions\ExceptionArgsPrinter;
use Phel\Runtime\Exceptions\Extractor\FilePositionExtractor;
use Phel\Runtime\Exceptions\Extractor\SourceMapExtractor;
use Phel\Runtime\Exceptions\TextExceptionPrinter;
use Phel\Runtime\RuntimeFacade;
use Phel\Runtime\RuntimeSingleton;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ReplCommandTest extends TestCase
{
    protected function setUp(): void
    {
        RuntimeSingleton::initializeNew(new GlobalEnvironment());
    }

    /**
     * @dataProvider providerIntegration
     */
    public function testIntegration(string $expectedOutput, InputLine ...$inputs): void
    {
        $io = $this->createReplTestIo();
        $io->setInputs(...$inputs);

        $repl = $this->createReplCommand($io);
        $repl->run();

        $replOutput = $io->getOutputString();

        self::assertEquals(trim($expectedOutput), trim($replOutput));
    }

    public function providerIntegration(): Generator
    {
        $fixturesDir = realpath(__DIR__ . '/Fixtures');

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
        $command = new ReplCommand(
            new RuntimeFacade(),
            $io,
            new CompilerFacade(),
            ColorStyle::noStyles(),
            Printer::nonReadable()
        );

        $command->addRuntimePath('phel\\', [__DIR__ . '/../../../../src/phel/']);

        return $command;
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
