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
        $this->createVendorWithPhel('0.53.0');

        $dirs = new ComposerVendorDirectoriesFinder($this->vendorDir)->findPhelSourceDirectories();

        self::assertSame([$this->vendorDir . '/acme/lib/src'], $dirs);
    }

    public function test_from_a_phar_warns_once_naming_both_versions_when_they_differ(): void
    {
        $this->createVendorWithPhel('v0.53.0');
        $warnings = [];
        $writer = static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        };

        $finder = new ComposerVendorDirectoriesFinder($this->vendorDir, true, 'v1.0.0', $writer);
        $finder->findPhelSourceDirectories();
        $finder->findPhelSourceDirectories();
        new ComposerVendorDirectoriesFinder($this->vendorDir, true, 'v1.0.0', $writer)->findPhelSourceDirectories();

        self::assertCount(1, $warnings);
        self::assertStringContainsString('phel.phar 1.0.0 ignores', $warnings[0]);
        self::assertStringContainsString('phel-lang/phel-lang 0.53.0', $warnings[0]);
    }

    public function test_the_warning_names_a_custom_vendor_dir(): void
    {
        $this->createVendorWithPhel('0.53.0');
        $warnings = [];

        new ComposerVendorDirectoriesFinder($this->vendorDir, true, 'v1.0.0', static function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        }, 'deps')->findPhelSourceDirectories();

        self::assertStringContainsString('installed in deps/', $warnings[0]);
        self::assertStringContainsString('Run ./deps/bin/phel', $warnings[0]);
    }

    public function test_from_a_phar_stays_silent_when_the_vendored_version_matches(): void
    {
        $this->createVendorWithPhel('1.0.0');
        $warnings = [];

        new ComposerVendorDirectoriesFinder($this->vendorDir, true, 'v1.0.0', static function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        })->findPhelSourceDirectories();

        self::assertSame([], $warnings);
    }

    public function test_a_dev_phar_build_matches_the_vendored_release(): void
    {
        $this->createVendorWithPhel('1.0.0');
        $warnings = [];

        new ComposerVendorDirectoriesFinder($this->vendorDir, true, 'v1.0.0-beta#abc1234', static function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        })->findPhelSourceDirectories();

        self::assertSame([], $warnings);
    }

    public function test_from_a_phar_names_an_unknown_vendored_version(): void
    {
        $this->createVendorWithPhel(null);
        $warnings = [];

        new ComposerVendorDirectoriesFinder($this->vendorDir, true, 'v1.0.0', static function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        })->findPhelSourceDirectories();

        self::assertCount(1, $warnings);
        self::assertStringContainsString('(unknown version)', $warnings[0]);
    }

    public function test_under_composer_a_vendored_phel_is_skipped_silently(): void
    {
        $this->createVendorWithPhel('0.53.0');
        $warnings = [];

        new ComposerVendorDirectoriesFinder($this->vendorDir, false, 'v1.0.0', static function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        })->findPhelSourceDirectories();

        self::assertSame([], $warnings);
    }

    private function createVendorWithPhel(?string $version): void
    {
        $this->vendorDir = sys_get_temp_dir() . '/phel-vendor-finder-' . bin2hex(random_bytes(6));
        foreach (['phel-lang/phel-lang/src/phel', 'acme/lib/src', 'composer'] as $dir) {
            mkdir($this->vendorDir . '/' . $dir, 0755, true);
        }

        file_put_contents($this->vendorDir . '/phel-lang/phel-lang/phel-config.php', "<?php return ['src-dirs' => ['src/phel']];\n");
        file_put_contents($this->vendorDir . '/acme/lib/phel-config.php', "<?php return ['src-dirs' => ['src']];\n");

        if ($version !== null) {
            file_put_contents(
                $this->vendorDir . '/composer/installed.json',
                json_encode(['packages' => [['name' => 'acme/lib', 'version' => '1.0.0'], ['name' => 'phel-lang/phel-lang', 'version' => $version]]]),
            );
        }
    }
}
