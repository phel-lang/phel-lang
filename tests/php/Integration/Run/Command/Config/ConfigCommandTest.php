<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Config;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Gacela\Framework\Testing\ContainerFixture;
use Phel\Config\PhelConfig;
use Phel\Run\Infrastructure\Command\ConfigCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class ConfigCommandTest extends TestCase
{
    use ContainerFixture;

    protected function setUp(): void
    {
        $this->resetContainer();
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValue(PhelConfig::SRC_DIRS, ['src/phel']);
            $config->addAppConfigKeyValue(PhelConfig::VENDOR_DIR, 'vendor');
            $config->addAppConfigKeyValue(PhelConfig::CACHE_DIR, '.phel/cache');
        });
    }

    protected function tearDown(): void
    {
        $this->cleanupContainerTempDirs();
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
