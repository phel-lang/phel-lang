<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop\Command\Export;

use Gacela\Framework\Gacela;
use Phel\Interop\InteropFacade;
use Phel\Interop\InteropFacadeInterface;
use PhelTest\Integration\Command\AbstractCommandTest;
use PhelTest\Integration\Util\DirectoryUtil;
use Symfony\Component\Console\Input\InputInterface;

final class ExportCommandTest extends AbstractCommandTest
{
    public static function setUpBeforeClass(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_export_command_multiple(): void
    {
        $command = $this->createInteropFacade()->getExportCommand();

        $this->expectOutputRegex('~Exported namespaces:~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Adder~');
        $this->expectOutputRegex('~TestCmdExportMultiple/Multiplier~');

        $command->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput()
        );

        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Adder.php');
        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Multiplier.php');

        DirectoryUtil::removeDir(__DIR__ . '/PhelGenerated/');
    }

    private function createInteropFacade(): InteropFacadeInterface
    {
        return new InteropFacade();
    }
}
