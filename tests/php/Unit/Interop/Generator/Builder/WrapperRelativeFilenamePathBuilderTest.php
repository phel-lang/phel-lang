<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop\Generator\Builder;

use Generator;
use Phel\Interop\Domain\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WrapperRelativeFilenamePathBuilderTest extends TestCase
{
    #[DataProvider('providerBuild')]
    public function test_build(string $phelNs, string $expected): void
    {
        $builder = new WrapperRelativeFilenamePathBuilder();

        self::assertSame($expected, $builder->build($phelNs));
    }

    public static function providerBuild(): Generator
    {
        yield 'simple name' => [
            'project\simple',
            'Project/Simple.php',
        ];

        yield 'filename with dash' => [
            'project\\the_file',
            'Project/TheFile.php',
        ];

        yield 'filename and phel namespace with dash' => [
            'the_project\\the_simple_file',
            'TheProject/TheSimpleFile.php',
        ];

        yield 'single segment' => [
            'export',
            'Export.php',
        ];

        yield 'dot separator' => [
            'test.export',
            'Test/Export.php',
        ];

        yield 'multiple dot separators' => [
            'a.b.c',
            'A/B/C.php',
        ];

        yield 'mixed dot separators and underscores' => [
            'my_lib.deep.export_util',
            'MyLib/Deep/ExportUtil.php',
        ];

        // Hyphens are NOT treated as separators: only `\`, `.` and `_` are.
        yield 'hyphen is left untouched' => [
            'test-lib',
            'Test-lib.php',
        ];
    }
}
