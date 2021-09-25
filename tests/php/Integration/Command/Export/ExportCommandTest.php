<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Export;

use Gacela\Framework\Gacela;
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
        $command = $this->createCommandFacade()->getExportCommand();

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
}
