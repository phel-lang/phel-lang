<?php

declare(strict_types=1);

namespace PhelTest\Integration\Interop\Command\Export;

use Phel\Interop\Infrastructure\Command\ExportCommand;
use Phel\Phel;
use PhelTest\Integration\Interop\Command\Export\PhelGenerated\TestCmdExportMultiple\Adder;
use PhelTest\Integration\Interop\Command\Export\PhelGenerated\TestCmdExportMultiple\Multiplier;
use PhelTest\Integration\Util\DirectoryUtil;
use PhelTest\Support\PerTestGacelaCache;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExportCommandTest extends TestCase
{
    protected function setUp(): void
    {
        // Runs in a separate process, where the Gacela-cache PHPUnit extension
        // does not apply; isolate the cache here so it cannot leak on disk.
        new PerTestGacelaCache()->isolate();
        DirectoryUtil::removeDir(__DIR__ . '/PhelGenerated/');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_export_command_multiple(): void
    {
        Phel::bootstrap(__DIR__);
        $command = new ExportCommand();

        ob_start();
        $command->run(
            $this->createStub(InputInterface::class),
            $this->stubOutput(),
        );
        $output = (string) ob_get_clean();

        self::assertMatchesRegularExpression('~Exported namespaces:~', $output);
        self::assertMatchesRegularExpression('~TestCmdExportMultiple/Adder~', $output);
        self::assertMatchesRegularExpression('~TestCmdExportMultiple/Multiplier~', $output);

        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Adder.php');
        self::assertFileExists(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Multiplier.php');

        self::assertSame(3, Adder::adder1(1, 2));
        self::assertSame(9, Multiplier::multiplier2(3, 3));

        // `:php/attr` metadata is emitted as a PHP attribute above the method
        $adder = (string) file_get_contents(__DIR__ . '/PhelGenerated/TestCmdExportMultiple/Adder.php');
        self::assertStringContainsString("#[\\My\\Routing\\Route('/add')]", $adder);
        self::assertSame(5, Adder::adder3(2, 3));

        // `:tag` metadata becomes native parameter/return types plus a docblock
        $expectedTypedAdder = <<<'TXT'
    /**
     * @param int $a
     * @param int $b
     * @return int
     */
    public static function typedAdder(int $a, int $b): int
TXT;
        self::assertStringContainsString($expectedTypedAdder, $adder);
        self::assertSame(5, Adder::typedAdder(2, 3));

        // multi-arity fns stay natively untyped but keep the return :tag in the docblock
        $expectedMultiAdder = <<<'TXT'
    /**
     * @param mixed ...$args
     * @return int
     */
    public static function multiAdder(...$args): mixed
TXT;
        self::assertStringContainsString($expectedMultiAdder, $adder);
        self::assertSame(7, Adder::multiAdder(3, 4));
    }

    private function stubOutput(): OutputInterface
    {
        $output = $this->createStub(OutputInterface::class);
        $output->method('writeln')
            ->willReturnCallback(static fn(string $str): int => print $str . PHP_EOL);

        return $output;
    }
}
