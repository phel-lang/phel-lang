<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Config;

use Gacela\Framework\Testing\GacelaTestCase;
use Phel\Config\PhelConfig;
use Phel\Run\Infrastructure\Command\ConfigCommand;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ConfigCommandTest extends GacelaTestCase
{
    protected function setUp(): void
    {
        $this->bootstrapGacelaWithConfig(__DIR__, [
            PhelConfig::SRC_DIRS => ['src/phel'],
            PhelConfig::VENDOR_DIR => 'vendor',
            PhelConfig::CACHE_DIR => '.phel/cache',
        ]);
    }

    public function test_default_output_lists_sources_and_effective_config(): void
    {
        $tester = new CommandTester(new ConfigCommand());

        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Sources:', $display);
        self::assertStringContainsString('Effective config:', $display);
        self::assertStringContainsString('src-dirs', $display);
        self::assertStringContainsString('src/phel', $display);
        // This fixture dir has no phel-config.php, so it is auto-detected.
        self::assertStringContainsString('not found', $display);
        // The fixture's src/phel does not exist on disk, so validation warns.
        self::assertStringContainsString('Validation:', $display);
        self::assertStringContainsString('WARNING', $display);
        self::assertStringContainsString("Source directory 'src/phel' does not exist", $display);
    }

    public function test_valid_config_reports_no_validation_issues(): void
    {
        // Point src/test dirs at the project root, which always exists, so the
        // config validates clean.
        $this->bootstrapGacelaWithConfig(__DIR__, [
            PhelConfig::SRC_DIRS => ['.'],
            PhelConfig::TEST_DIRS => ['.'],
        ]);

        $tester = new CommandTester(new ConfigCommand());
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Validation:', $display);
        self::assertStringContainsString('no issues found', $display);
    }

    public function test_absolute_src_dir_is_reported_in_the_validation_section(): void
    {
        $this->bootstrapGacelaWithConfig(__DIR__, [
            PhelConfig::SRC_DIRS => ['/absolute/src'],
        ]);

        $tester = new CommandTester(new ConfigCommand());
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        // Surfacing is non-fatal: `phel config` stays informational (exit 0).
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Validation:', $display);
        self::assertStringContainsString('ERROR', $display);
        self::assertStringContainsString('/absolute/src', $display);
    }

    /**
     * The exit code is deliberately 0 (see `ConfigCommand::writeValidation`),
     * so the output is the only channel left to say the config has errors. It
     * has to be unmistakable rather than one more line of dumped content, and
     * it has to name the command that does fail on them.
     */
    public function test_config_errors_are_summarised_and_point_at_doctor(): void
    {
        $this->bootstrapGacelaWithConfig(__DIR__, [
            PhelConfig::SRC_DIRS => ['/absolute/src'],
            PhelConfig::VENDOR_DIR => '/absolute/vendor',
        ]);

        $tester = new CommandTester(new ConfigCommand());
        $exitCode = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('2 configuration error(s) found.', $display);
        self::assertStringContainsString('still exits 0', $display);
        self::assertStringContainsString('phel doctor', $display);
    }

    public function test_warnings_alone_do_not_produce_an_error_summary(): void
    {
        // The fixture's src/phel does not exist, which warns but never errors.
        $tester = new CommandTester(new ConfigCommand());
        $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('WARNING', $display);
        self::assertStringNotContainsString('configuration error(s) found', $display);
    }

    public function test_format_json_emits_only_valid_json(): void
    {
        $tester = new CommandTester(new ConfigCommand());

        $exitCode = $tester->execute(['--format' => 'json']);

        self::assertSame(0, $exitCode);

        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertSame(['src/phel'], $decoded[PhelConfig::SRC_DIRS]);
        self::assertSame('vendor', $decoded[PhelConfig::VENDOR_DIR]);
        self::assertArrayNotHasKey('Sources:', $decoded);
    }

    public function test_deprecated_json_flag_still_emits_json_with_a_warning(): void
    {
        $tester = new CommandTester(new ConfigCommand());

        $exitCode = $tester->execute(['--json' => true], ['capture_stderr_separately' => true]);

        self::assertSame(0, $exitCode);
        // stdout stays valid JSON; the deprecation notice goes to stderr.
        $decoded = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['src/phel'], $decoded[PhelConfig::SRC_DIRS]);
        self::assertStringContainsString('--json is deprecated', $tester->getErrorOutput());
    }
}
