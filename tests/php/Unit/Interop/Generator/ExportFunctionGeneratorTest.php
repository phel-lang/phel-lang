<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop\Generator;

use Phel\Interop\Generator\ExportFunctionGenerator;
use Phel\Interop\Generator\FunctionToExport;
use Phel\Lang\FnInterface;
use PHPUnit\Framework\TestCase;

final class ExportFunctionGeneratorTest extends TestCase
{
    public function testGenerateWrapper(): void
    {
        $generator = new ExportFunctionGenerator('.');
        $phelNs = 'phel\\local_test';

        $functionToExport = new FunctionToExport(new class() implements FnInterface {
            public const BOUND_TO = 'phel\\local_test\\adder_example';

            public function __invoke(int $a, int ...$b)
            {
                return $a + array_sum($b);
            }
        });

        $wrapper = $generator->generateWrapper($phelNs, $functionToExport);

        self::assertSame('./LocalTest.php', $wrapper->destinyPath());

        $expectedCompiledPhp = <<<'TXT'
<?php declare(strict_types=1);

namespace Generated\Phel;

use Phel\Interop\PhelCallerTrait;

final class LocalTest
{
    use PhelCallerTrait;

    /**
     * @return mixed
     */
    public function adderExample($a, ...$b)
    {
        return $this->callPhel('phel\local_test', 'adder-example', $a, ...$b);
    }

}
TXT;
        self::assertSame($expectedCompiledPhp, $wrapper->compiledPhp());
    }
}
