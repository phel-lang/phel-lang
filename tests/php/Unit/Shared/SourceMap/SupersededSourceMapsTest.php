<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\SourceMap;

use Phel\Shared\SourceMap\SupersededSourceMaps;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_put_contents;
use function glob;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class SupersededSourceMapsTest extends TestCase
{
    private const string FIRST_VERSION = "<?php\n// /src/main.phel\n// ;;AACA\nreturn 1;\n";

    private const string SECOND_VERSION = "<?php\n// /src/main.phel\n// ;;AAGA\nreturn 2;\n";

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phel-superseded-maps-' . uniqid();
        mkdir($this->dir);
        SupersededSourceMaps::reset();
    }

    protected function tearDown(): void
    {
        SupersededSourceMaps::reset();
        array_map(unlink(...), glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function test_keeps_the_header_of_a_loaded_file_before_it_is_overwritten(): void
    {
        $file = $this->loadedFile('loaded.php');

        SupersededSourceMaps::keepBeforeOverwrite($file, self::SECOND_VERSION);

        self::assertTrue(SupersededSourceMaps::has($file));
        self::assertSame(
            ["<?php\n", "// /src/main.phel\n", "// ;;AACA\n", "return 1;\n"],
            SupersededSourceMaps::headerOf($file),
        );
    }

    public function test_ignores_a_file_this_process_never_loaded(): void
    {
        $file = $this->dir . '/not-loaded.php';
        file_put_contents($file, self::FIRST_VERSION);

        SupersededSourceMaps::keepBeforeOverwrite($file, self::SECOND_VERSION);

        self::assertFalse(SupersededSourceMaps::has($file));
    }

    public function test_ignores_an_overwrite_with_the_same_code(): void
    {
        $file = $this->loadedFile('same.php');

        SupersededSourceMaps::keepBeforeOverwrite($file, self::FIRST_VERSION);

        self::assertNull(SupersededSourceMaps::headerOf($file));
    }

    public function test_the_first_loaded_version_wins(): void
    {
        $file = $this->loadedFile('twice.php');

        SupersededSourceMaps::keepBeforeOverwrite($file, self::SECOND_VERSION);
        file_put_contents($file, self::SECOND_VERSION);
        SupersededSourceMaps::keepBeforeOverwrite($file, "<?php\n// /src/main.phel\n// ;;AAMA\nreturn 3;\n");

        self::assertSame("// ;;AACA\n", SupersededSourceMaps::headerOf($file)[2] ?? null);
    }

    public function test_has_nothing_for_a_missing_file(): void
    {
        self::assertFalse(SupersededSourceMaps::has($this->dir . '/missing.php'));
    }

    private function loadedFile(string $name): string
    {
        $file = $this->dir . '/' . $name;
        file_put_contents($file, self::FIRST_VERSION);
        require $file;

        return $file;
    }
}
