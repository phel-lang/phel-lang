<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Export;

use Phel\Command\CommandConfig;
use Phel\Command\CommandFactory;
use Phel\Command\CommandFactoryInterface;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\CompilerFactory;
use Phel\Formatter\FormatterFactory;
use Phel\Interop\InteropConfig;
use Phel\Interop\InteropFactory;
use Phel\Runtime\RuntimeFactory;
use Phel\Runtime\RuntimeInterface;
use PhelTest\Integration\Util\DirectoryUtil;
use PHPUnit\Framework\TestCase;

final class ExportCommandTest extends TestCase
{
    public function testExportCommandMultiple(): void
    {
        $currentDir = __DIR__ . '/Fixtures/test-cmd-export-multiple/';

        $runtime = $this->createRuntime();
        $runtime->addPath('test-cmd-export-multiple\\', [$currentDir . 'src']);
        $runtime->addPath('phel\\', [__DIR__ . '/../../../src/phel']);
        $runtime->loadNs('phel\core');

        $command = $this
            ->createCommandFactory($currentDir)
            ->createExportCommand($runtime);

        $this->expectOutputRegex('~Exported namespaces:~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Adder~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Multiplier~');

        $command->run();

        self::assertFileExists("{$currentDir}/PhelGenerated/TestCmdExportMultiple/Adder.php");
        self::assertFileExists("{$currentDir}/PhelGenerated/TestCmdExportMultiple/Multiplier.php");

        DirectoryUtil::removeDir("{$currentDir}/PhelGenerated/");
    }

    private function createRuntime(): RuntimeInterface
    {
        return RuntimeFactory::initializeNew(new GlobalEnvironment());
    }

    private function createCommandFactory(string $currentDir): CommandFactoryInterface
    {
        $compilerFactory = new CompilerFactory();

        return new CommandFactory(
            $currentDir,
            new CommandConfig($currentDir),
            $compilerFactory,
            new FormatterFactory($compilerFactory),
            new InteropFactory(new InteropConfig($currentDir))
        );
    }
}
