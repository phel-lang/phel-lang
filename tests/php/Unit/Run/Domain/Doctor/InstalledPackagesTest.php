<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Doctor;

use Phel\Run\Domain\Doctor\InstalledPackage;
use Phel\Run\Domain\Doctor\InstalledPackages;
use PHPUnit\Framework\TestCase;

final class InstalledPackagesTest extends TestCase
{
    public function test_reads_the_composer_2_format(): void
    {
        self::assertEquals(
            ['acme/lib' => new InstalledPackage('v1.2.0')],
            InstalledPackages::fromJson('{"packages":[{"name":"acme/lib","version":"v1.2.0"}],"dev":true}'),
        );
    }

    public function test_reads_the_composer_1_format(): void
    {
        self::assertEquals(['acme/lib' => new InstalledPackage('1.0.0')], InstalledPackages::fromJson('[{"name":"acme/lib","version":"1.0.0"}]'));
    }

    public function test_reads_the_source_reference_then_the_dist_one(): void
    {
        self::assertEquals(
            ['a/x' => new InstalledPackage('dev-main', 'src1'), 'b/y' => new InstalledPackage('1.0.0', 'dist2')],
            InstalledPackages::fromJson('[{"name":"a/x","version":"dev-main","source":{"reference":"src1"},"dist":{"reference":"dist1"}},{"name":"b/y","version":"1.0.0","dist":{"reference":"dist2"}}]'),
        );
    }

    public function test_ignores_invalid_json_and_incomplete_entries(): void
    {
        self::assertSame([], InstalledPackages::fromJson('not json'));
        self::assertSame([], InstalledPackages::fromJson('{"packages":[{"name":"acme/lib"}]}'));
    }
}
