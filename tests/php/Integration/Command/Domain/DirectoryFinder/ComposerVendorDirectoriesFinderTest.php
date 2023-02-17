<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Domain\DirectoryFinder;

use Exception;
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

    public function test_trigger_warning_when_wrong_type(): void
    {
        // @see https://github.com/sebastianbergmann/phpunit/issues/5062#issuecomment-1416362657
        set_error_handler(static function (int $errno, string $errstr): void {
            throw new Exception($errstr, $errno);
        }, E_USER_NOTICE);

        $this->expectExceptionMessageMatches('#The ".*" must return an array or a PhelConfig object. Path: .*#');

        $finder = new ComposerVendorDirectoriesFinder(vendorDirectory: __DIR__ . '/wrong-testing-vendor');
        $finder->findPhelSourceDirectories();

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
