<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Doctor;

use Phel\Run\Domain\Doctor\VendorPhelLibraries;
use PHPUnit\Framework\TestCase;

final class VendorPhelLibrariesTest extends TestCase
{
    public function test_groups_vendor_source_dirs_by_package(): void
    {
        self::assertSame(
            ['acme/lib' => ['vendor/acme/lib/src', 'vendor/acme/lib/lib']],
            VendorPhelLibraries::group(
                ['/app/src', '/app/vendor/acme/lib/src', '/app/vendor/acme/lib/lib'],
                '/app/vendor',
                '/app/vendor',
                'vendor',
            ),
        );
    }

    public function test_matches_a_symlinked_vendor_dir_either_way(): void
    {
        self::assertSame(
            ['acme/lib' => ['vendor/acme/lib/src'], 'zeta/x' => ['vendor/zeta/x/src']],
            VendorPhelLibraries::group(
                ['/app/vendor/acme/lib/src', '/real/vendor/zeta/x/src'],
                '/app/vendor',
                '/real/vendor',
                'vendor',
            ),
        );
    }

    public function test_leaves_out_phel_itself(): void
    {
        self::assertSame(
            [],
            VendorPhelLibraries::group(['/app/vendor/phel-lang/phel-lang/src/phel'], '/app/vendor', '/app/vendor', 'vendor'),
        );
    }

    public function test_names_a_custom_vendor_dir_and_windows_separators(): void
    {
        self::assertSame(
            ['acme/lib' => ['deps/acme/lib/src']],
            VendorPhelLibraries::group(['C:\\app\\deps\\acme\\lib\\src'], 'C:\\app\\deps', 'C:\\app\\deps', 'deps'),
        );
    }
}
