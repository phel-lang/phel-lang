<?php

declare(strict_types=1);

namespace PhelTest\Unit\Config;

use ParseError;
use Phel\Config\ConfigLoadException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function file_put_contents;
use function random_bytes;
use function sys_get_temp_dir;

final class ConfigLoadExceptionTest extends TestCase
{
    private const string GACELA_WRONG_TYPE = 'The PHP config file must return an array or a JsonSerializable object!';

    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/phel-config-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($this->configPath, "<?php\nreturn new \\Phel\\Config\\PhelConfig();\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->configPath);
    }

    public function test_wraps_the_wrong_return_type_error(): void
    {
        $original = new RuntimeException(self::GACELA_WRONG_TYPE);

        $wrapped = ConfigLoadException::wrapIfConfigError($original, $this->configPath);

        self::assertInstanceOf(ConfigLoadException::class, $wrapped);
        self::assertSame($original, $wrapped->getPrevious());
        self::assertStringContainsString($this->configPath, $wrapped->getMessage());
        self::assertStringContainsString('must `return` a PhelConfig', $wrapped->getMessage());
        // The raw-array form is equally valid, so the guidance must mention it.
        self::assertStringContainsString('config array', $wrapped->getMessage());
    }

    public function test_wraps_a_parse_error_originating_from_the_config_file(): void
    {
        $broken = sys_get_temp_dir() . '/phel-broken-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($broken, "<?php\nreturn new ;\n");

        $caught = null;
        try {
            include $broken;
        } catch (ParseError $parseError) {
            $caught = $parseError;
        }

        try {
            self::assertInstanceOf(ParseError::class, $caught);
            $wrapped = ConfigLoadException::wrapIfConfigError($caught, $broken);

            self::assertInstanceOf(ConfigLoadException::class, $wrapped);
            self::assertStringContainsString($broken, $wrapped->getMessage());
        } finally {
            @unlink($broken);
        }
    }

    public function test_wraps_an_exception_thrown_from_the_config_file(): void
    {
        // A config file may deliberately `throw` (e.g. a guard for a required
        // env var). A thrown exception is an evaluation error too, so it must
        // be wrapped, not surface as an uncaught fatal.
        $broken = sys_get_temp_dir() . '/phel-throws-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($broken, "<?php\n\nthrow new \\RuntimeException('APP_KEY missing');\n");

        $caught = null;
        try {
            include $broken;
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException;
        }

        try {
            self::assertInstanceOf(RuntimeException::class, $caught);
            $wrapped = ConfigLoadException::wrapIfConfigError($caught, $broken);

            self::assertInstanceOf(ConfigLoadException::class, $wrapped);
            self::assertSame($caught, $wrapped->getPrevious());
            self::assertStringContainsString($broken, $wrapped->getMessage());
            self::assertStringContainsString('APP_KEY missing', $wrapped->getMessage());
        } finally {
            @unlink($broken);
        }
    }

    public function test_returns_unrelated_errors_unchanged(): void
    {
        $original = new RuntimeException('something unrelated blew up');

        self::assertSame($original, ConfigLoadException::wrapIfConfigError($original, $this->configPath));
    }

    public function test_does_not_wrap_when_the_config_path_does_not_exist(): void
    {
        // Even a config-shaped message is left alone when there is no file:
        // the failure cannot be about a config that is not there.
        $original = new RuntimeException(self::GACELA_WRONG_TYPE);

        self::assertSame(
            $original,
            ConfigLoadException::wrapIfConfigError($original, '/no/such/phel-config.php'),
        );
    }
}
