<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop\Generator;

use Phel\Interop\Domain\Generator\Builder\CompiledPhpClassBuilder;
use Phel\Interop\Domain\Generator\Builder\CompiledPhpMethodBuilder;
use Phel\Interop\Domain\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use Phel\Interop\Domain\Generator\WrapperGenerator;
use Phel\Interop\Domain\Generator\WrapperGeneratorInterface;
use Phel\Interop\Domain\ReadModel\FunctionToExport;
use Phel\Lang\FnInterface;
use PHPUnit\Framework\TestCase;

final class WrapperGeneratorTest extends TestCase
{
    public function test_generate_compiled_php(): void
    {
        $generator = $this->createWrapperGenerator();
        $phelNs = 'custom_namespace\\file_name_example';

        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'custom_namespace\\file_name_example\\phel_function_example';

            public function __invoke(int $a, int ...$b): int|float
            {
                return $a + array_sum($b);
            }
        });

        $wrapper = $generator->generateCompiledPhp($phelNs, [$functionToExport]);

        self::assertSame('CustomNamespace/FileNameExample.php', $wrapper->relativeFilenamePath());

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

    private function createWrapperGenerator(): WrapperGeneratorInterface
    {
        return new WrapperGenerator(
            new CompiledPhpClassBuilder(
                prefixNamespace: 'PhelGenerated',
                methodBuilder: new CompiledPhpMethodBuilder(),
            ),
            new WrapperRelativeFilenamePathBuilder(),
        );
    }
}
