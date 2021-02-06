<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop\Generator;

use Phel\Interop\InteropFactory;
use Phel\Interop\ReadModel\FunctionToExport;
use Phel\Lang\FnInterface;
use PHPUnit\Framework\TestCase;

final class ExportFunctionGeneratorTest extends TestCase
{
    private InteropFactory $interopFactory;

    public function setUp(): void
    {
        $this->interopFactory = new InteropFactory();
    }

    public function testGenerateWrapper(): void
    {
        $generator = $this->interopFactory->createWrapperGenerator('.');
        $phelNs = 'custom_namespace\\file_name_example';

        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'custom_namespace\\file_name_example\\phel_function_example';

            public function __invoke(int $a, int ...$b)
            {
                return $a + array_sum($b);
            }
        });

        $wrapper = $generator->generateCompiledPhp($phelNs, $functionToExport);

        self::assertSame('.', $wrapper->destinationDir());
        self::assertSame('./CustomNamespace', $wrapper->dir());
        self::assertSame('./CustomNamespace/FileNameExample.php', $wrapper->absolutePath());

        $expectedCompiledPhp = <<<'TXT'
<?php declare(strict_types=1);

namespace PhelGenerated\CustomNamespace;

use Phel\Interop\PhelCallerTrait;

/**
 * THIS FILE IS AUTO-GENERATED, DO NOT CHANGE ANYTHING IN THIS FILE
 */
final class FileNameExample
{
    use PhelCallerTrait;

    /**
     * @return mixed
     */
    public static function phelFunctionExample($a, ...$b)
    {
        return self::callPhel('custom-namespace\\file-name-example', 'phel-function-example', $a, ...$b);
    }

}
TXT;
        self::assertSame($expectedCompiledPhp, $wrapper->compiledPhp());
    }
}
