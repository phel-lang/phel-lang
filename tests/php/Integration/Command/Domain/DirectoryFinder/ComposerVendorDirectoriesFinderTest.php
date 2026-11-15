<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Domain\DirectoryFinder;

use Phel\Command\Infrastructure\ComposerVendorDirectoriesFinder;
use Phel\Phel;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

final class ComposerVendorDirectoriesFinderTest extends TestCase
{
    use RemoveDirTrait;

    private string $vendorDir = '';

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
    }

    protected function tearDown(): void
    {
        if ($this->vendorDir !== '') {
            $this->removeDir($this->vendorDir);
        }
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

    public function test_skips_a_vendored_phel_and_keeps_other_libraries(): void
    {
        $this->createVendorWithPhel();

        $dirs = new ComposerVendorDirectoriesFinder($this->vendorDir)->findPhelSourceDirectories();

        self::assertSame([$this->vendorDir . '/acme/lib/src'], $dirs);
    }

    private function createVendorWithPhel(): void
    {
        $this->vendorDir = sys_get_temp_dir() . '/phel-vendor-finder-' . bin2hex(random_bytes(6));
        foreach (['phel-lang/phel-lang/src/phel', 'acme/lib/src'] as $dir) {
            mkdir($this->vendorDir . '/' . $dir, 0755, true);
        }

        file_put_contents($this->vendorDir . '/phel-lang/phel-lang/phel-config.php', "<?php return ['src-dirs' => ['src/phel']];\n");
        file_put_contents($this->vendorDir . '/acme/lib/phel-config.php', "<?php return ['src-dirs' => ['src']];\n");
    }
}
