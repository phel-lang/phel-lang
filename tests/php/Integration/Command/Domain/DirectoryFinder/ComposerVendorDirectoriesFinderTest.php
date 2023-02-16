<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Domain\DirectoryFinder;

use Phel\Command\Domain\Finder\ComposerVendorDirectoriesFinder;
use Phel\Config\PhelConfigException;
use PHPUnit\Framework\TestCase;

final class ComposerVendorDirectoriesFinderTest extends TestCase
{
    public function test_exception_when_wrong_type(): void
    {
        $this->expectExceptionObject(PhelConfigException::wrongType());

        $finder = new ComposerVendorDirectoriesFinder(vendorDirectory: __DIR__ . '/wrong-testing-vendor');
        $finder->findPhelSourceDirectories();
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
