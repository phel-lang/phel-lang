<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Doctor;

use Phel\Run\Domain\Doctor\InstallationReport;
use Phel\Run\Domain\Doctor\InstallMode;
use PHPUnit\Framework\TestCase;

use function implode;

final class InstallationReportTest extends TestCase
{
    public function test_names_the_install_mode_version_and_location(): void
    {
        $text = $this->render(new InstallationReport(InstallMode::Phar, '/opt/phel.phar', 'v1.0.0', false, false, [], []));

        self::assertStringContainsString('Phel: 1.0.0 from phel.phar (/opt/phel.phar)', $text);
    }

    public function test_reports_the_project_autoloader_state(): void
    {
        self::assertStringContainsString(
            'no vendor/autoload.php',
            $this->render(new InstallationReport(InstallMode::Phar, '/p', '1.0.0', false, false, [], [])),
        );
        self::assertStringContainsString(
            'OK</info> vendor/autoload.php loaded',
            $this->render(new InstallationReport(InstallMode::Phar, '/p', '1.0.0', true, true, [], [])),
        );
        self::assertStringContainsString(
            'exists but was not loaded',
            $this->render(new InstallationReport(InstallMode::Phar, '/p', '1.0.0', true, false, [], [])),
        );
    }

    public function test_lists_phel_libraries_with_their_source_dirs(): void
    {
        $text = $this->render(new InstallationReport(
            InstallMode::Composer,
            '/p',
            '1.0.0',
            true,
            true,
            ['zeta/lib' => ['vendor/zeta/lib/src'], 'acme/http' => ['vendor/acme/http/src', 'vendor/acme/http/lib']],
            [],
        ));

        self::assertStringContainsString("   - acme/http (vendor/acme/http/src, vendor/acme/http/lib)\n   - zeta/lib (vendor/zeta/lib/src)", $text);
    }

    public function test_from_a_phar_warns_about_a_vendored_phel(): void
    {
        $text = $this->render(new InstallationReport(InstallMode::Phar, '/p', '1.0.0', true, true, [], ['phel-lang/phel-lang' => 'v0.53.0']));

        self::assertStringContainsString('vendor/ also holds phel-lang/phel-lang 0.53.0', $text);
    }

    public function test_under_composer_the_vendored_phel_is_the_running_one(): void
    {
        $text = $this->render(new InstallationReport(InstallMode::Composer, '/p', '1.0.0', true, true, [], ['phel-lang/phel-lang' => '1.0.0']));

        self::assertStringNotContainsString('WARN', $text);
    }

    public function test_warns_about_php_packages_shadowed_by_the_phar(): void
    {
        $text = $this->render(new InstallationReport(
            InstallMode::Phar,
            '/p',
            '1.0.0',
            true,
            true,
            [],
            ['symfony/console' => 'v7.1.0', 'psr/log' => '3.0.0', 'acme/only-here' => '1.0.0'],
            ['symfony/console' => 'v7.3.0', 'psr/log' => '3.0.0'],
        ));

        self::assertStringContainsString('symfony/console 7.1.0 in vendor/ differs from the 7.3.0 bundled in phel.phar', $text);
        self::assertStringNotContainsString('psr/log', $text);
        self::assertStringNotContainsString('acme/only-here', $text);
    }

    public function test_names_a_custom_vendor_dir(): void
    {
        $text = $this->render(new InstallationReport(InstallMode::Composer, '/p', '1.0.0', true, true, ['acme/lib' => ['deps/acme/lib/src']], [], vendorName: 'deps'));

        self::assertStringContainsString('deps/autoload.php loaded', $text);
        self::assertStringContainsString('Phel libraries in deps/:', $text);
    }

    public function test_reports_a_vendor_scan_that_failed(): void
    {
        $text = $this->render(new InstallationReport(InstallMode::Composer, '/p', '1.0.0', true, true, [], [], libraryScanError: 'syntax error in vendor/acme/lib/phel-config.php'));

        self::assertStringContainsString('could not read them: syntax error in vendor/acme/lib/phel-config.php', $text);
    }

    private function render(InstallationReport $report): string
    {
        return implode("\n", $report->lines());
    }
}
