<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Testing\ContainerFixture;
use Phel\Config\ConfigLoadException;
use Phel\Config\PhelConfig;
use Phel\Phel;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

/**
 * End-to-end check that {@see Phel::bootstrap()} turns a broken
 * `phel-config.php` into a friendly {@see ConfigLoadException} instead of a
 * cryptic underlying error.
 */
final class ConfigLoadErrorTest extends TestCase
{
    use ContainerFixture;

    private string $dir;

    protected function setUp(): void
    {
        $this->resetContainer();
        Phel::resetAutoDetectedConfig();
        $this->dir = sys_get_temp_dir() . '/phel-badcfg-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        Phel::resetAutoDetectedConfig();
        $this->removeDir($this->dir);
        $this->cleanupContainerTempDirs();
    }

    public function test_bootstrap_wraps_a_non_config_return_value(): void
    {
        file_put_contents($this->dir . '/phel-config.php', "<?php\n\nreturn 'not a config';\n");

        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('phel-config.php');

        Phel::bootstrap($this->dir);
    }

    public function test_bootstrap_accepts_a_raw_array_config(): void
    {
        // A plain array is a valid config too (PhelConfig is recommended, not
        // required): it must load and be readable, not raise a load error.
        file_put_contents($this->dir . '/phel-config.php', "<?php\n\nreturn ['src-dirs' => ['custom-src']];\n");

        Phel::bootstrap($this->dir);

        self::assertSame(['custom-src'], Config::getInstance()->get(PhelConfig::SRC_DIRS));
    }

    public function test_bootstrap_wraps_an_exception_thrown_from_the_config_file(): void
    {
        // A config file that throws (not a PHP Error, a deliberate exception)
        // is an evaluation error and must become a friendly ConfigLoadException
        // rather than an uncaught fatal with a stack trace.
        file_put_contents(
            $this->dir . '/phel-config.php',
            "<?php\n\nthrow new \\RuntimeException('APP_KEY missing');\n",
        );

        $this->expectException(ConfigLoadException::class);
        $this->expectExceptionMessage('phel-config.php');

        Phel::bootstrap($this->dir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
