<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Gacela\Framework\Gacela;
use Phel\Build\BuildFacade;
use Phel\Build\BuildFacadeInterface;
use Phel\Build\Compile\BuildOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CompileCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_compile_project(): void
    {
        $command = $this->createBuildFacade()->getCompileCommand();

        $command->run(
            $this->stubInput(new BuildOptions(false, false)),
            $this->stubOutput()
        );

        self::assertFileExists(__DIR__ . '/out/phel/core.phel');
        self::assertFileExists(__DIR__ . '/out/phel/core.php');
        self::assertFileExists(__DIR__ . '/out/hello.phel');
        self::assertFileExists(__DIR__ . '/out/hello.php');
    }

    private function createBuildFacade(): BuildFacadeInterface
    {
        return new BuildFacade();
    }

    private function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(fn (string $str) => print $str . PHP_EOL);

        return $output;
    }

    private function stubInput(BuildOptions $buildOptions): InputInterface
    {
        $input = [];
        if (!$buildOptions->getEnableSourceMap()) {
            $input['--no-source-map'] = true;
        }
        if (!$buildOptions->getEnableCache()) {
            $input['--no-cache'] = true;
        }

        return new ArrayInput($input);
    }
}
