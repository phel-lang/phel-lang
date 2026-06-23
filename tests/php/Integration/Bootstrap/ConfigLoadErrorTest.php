<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use Gacela\Framework\Testing\ContainerFixture;
use Phel\Config\ConfigLoadException;
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
