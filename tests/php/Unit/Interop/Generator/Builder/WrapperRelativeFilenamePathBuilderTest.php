<?php

declare(strict_types=1);

namespace PhelTest\Unit\Interop\Generator\Builder;

use Generator;
use Phel\Interop\Generator\Builder\WrapperRelativeFilenamePathBuilder;
use PHPUnit\Framework\TestCase;

final class WrapperRelativeFilenamePathBuilderTest extends TestCase
{
    /**
     * @dataProvider providerBuild
     */
    public function test_build(string $phelNs, string $expected): void
    {
        $builder = new WrapperRelativeFilenamePathBuilder();

        self::assertSame($expected, $builder->build($phelNs));
    }

    public function providerBuild(): Generator
    {
        yield 'simple name' => [
            'phelNs' => 'project\simple',
            'expected' => 'Project/Simple.php',
        ];

        yield 'filename with dash' => [
            'phelNs' => 'project\\the_file',
            'expected' => 'Project/TheFile.php',
        ];

        yield 'filename and phel namespace with dash' => [
            'phelNs' => 'the_project\\the_simple_file',
            'expected' => 'TheProject/TheSimpleFile.php',
        ];
    }
}
