<?php

declare(strict_types=1);

namespace PhelTest\Unit\Config;

use Phel\Config\PhelConfig;
use Phel\Config\StrictPhpConfigReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function file_put_contents;
use function random_bytes;
use function sys_get_temp_dir;

final class StrictPhpConfigReaderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/phel-strict-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_reads_a_plain_array_config(): void
    {
        file_put_contents($this->path, "<?php\nreturn ['src-dirs' => ['custom-src']];\n");

        self::assertSame(['src-dirs' => ['custom-src']], new StrictPhpConfigReader()->read($this->path));
    }

    public function test_reads_a_phel_config_instance_via_json_serialize(): void
    {
        file_put_contents($this->path, "<?php\nreturn new \\Phel\\Config\\PhelConfig()->withSrcDirs(['app']);\n");

        $values = new StrictPhpConfigReader()->read($this->path);

        self::assertSame(['app'], $values[PhelConfig::SRC_DIRS]);
    }

    public function test_rejects_a_null_return_instead_of_coercing_to_empty(): void
    {
        // Gacela's PhpConfigReader maps `return null;` to `[]`; the strict
        // reader must refuse it so a forgotten/typo'd return is not silently
        // dropped (the #2642 regression).
        file_put_contents($this->path, "<?php\nreturn null;\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must return an array or a JsonSerializable');

        new StrictPhpConfigReader()->read($this->path);
    }

    public function test_still_rejects_a_scalar_return(): void
    {
        file_put_contents($this->path, "<?php\nreturn 'nope';\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must return an array or a JsonSerializable');

        new StrictPhpConfigReader()->read($this->path);
    }

    public function test_returns_empty_for_a_missing_file(): void
    {
        self::assertSame([], new StrictPhpConfigReader()->read('/no/such/phel-config.php'));
    }

    public function test_a_repeated_read_of_a_throwing_config_raises_again(): void
    {
        // Load-bearing for `Phel::readAppModulePaths()`, which evaluates
        // `phel-config.php` up front and drops the failure on purpose: the
        // reader must re-evaluate the very same path and raise again, otherwise
        // that drop becomes a silent swallow. Only `include_once` would make
        // the second evaluation a no-op, so the reader must keep plain
        // `include`.
        file_put_contents($this->path, "<?php\nthrow new \\RuntimeException('boom');\n");

        $reader = new StrictPhpConfigReader();

        try {
            $reader->read($this->path);
            self::fail('The first read was expected to raise.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('boom', $runtimeException->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $reader->read($this->path);
    }
}
