<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Domain\DirectoryFinder;

use Gacela\Framework\Gacela;
use Phel\Command\Domain\Finder\ComposerVendorDirectoriesFinder;
use Phel\Phel;
use PHPUnit\Framework\TestCase;

final class ComposerVendorDirectoriesFinderTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__, Phel::configFn());
    }

    public function test_trigger_notice_when_wrong_type(): void
    {
        $errors = [];

        // @see https://github.com/sebastianbergmann/phpunit/issues/5062#issuecomment-1416362657
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): void {
            $errors[] = [
                'errno' => $errno,
                'errstr' => $errstr,
            ];
        }, E_USER_NOTICE);

        $finder = new ComposerVendorDirectoriesFinder(vendorDirectory: __DIR__ . '/wrong-testing-vendor');
        $dirs = $finder->findPhelSourceDirectories();

        self::assertCount(1, $errors);
        self::assertMatchesRegularExpression('#The ".*" must return an array or a PhelConfig object. Path: .*#', $errors[0]['errstr']);
        self::assertSame(1024, $errors[0]['errno']);

        self::assertCount(1, $dirs);
        self::assertMatchesRegularExpression('#.*/wrong-testing-vendor/root-1/root-3/custom-src-3#', $dirs[0]);

        restore_error_handler();
    }

    public function test_find_phel_source_directories(): void
    {
        $finder = new ComposerVendorDirectoriesFinder(vendorDirectory: __DIR__ . '/testing-vendor');
        $dirs = $finder->findPhelSourceDirectories();

        self::assertCount(2, $dirs);
        self::assertMatchesRegularExpression('#.*/testing-vendor/root-1/root-2/custom-src-2#', $dirs[0]);
        self::assertMatchesRegularExpression('#.*/testing-vendor/root-1/root-3/custom-src-3#', $dirs[1]);
    }
}
