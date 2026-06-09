<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Infrastructure;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\SourceMapInformation;
use Phel\Command\Infrastructure\SourceMapExtractor;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_put_contents;
use function glob;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class SourceMapExtractorTest extends TestCase
{
    private string $dir;

    private SourceMapExtractor $extractor;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phel-source-map-extractor-' . uniqid();
        mkdir($this->dir);
        $this->extractor = new SourceMapExtractor();
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function test_extracts_inline_metadata_from_eval_temp_file(): void
    {
        $file = $this->dir . '/__phel_abc.php';
        file_put_contents($file, "<?php\n// /src/main.phel\n// ;;AACA\n\$x = 1;\n");

        $info = $this->extractor->extractFromFile($file);

        self::assertSame('/src/main.phel', $info->filename());
        self::assertSame('AACA', $info->mappings());
        self::assertSame(4, $info->codeStartLine());
    }

    public function test_extracts_inline_metadata_below_declare_statement(): void
    {
        $file = $this->dir . '/__phel_ticks.php';
        file_put_contents($file, "<?php\ndeclare(ticks=1);\n// /src/main.phel\n// ;;AACA\n\$x = 1;\n");

        $info = $this->extractor->extractFromFile($file);

        self::assertSame('/src/main.phel', $info->filename());
        self::assertSame('AACA', $info->mappings());
        self::assertSame(5, $info->codeStartLine());
    }

    public function test_extracts_sibling_map_and_phel_files_from_built_output(): void
    {
        $file = $this->dir . '/main.php';
        file_put_contents($file, "<?php declare(strict_types=1);\n\$x = 1;\n");
        file_put_contents($file . '.map', ';AACA');
        file_put_contents($this->dir . '/main.phel', "(ns app\\main)\n(def x 1)\n");

        $info = $this->extractor->extractFromFile($file);

        self::assertSame($this->dir . '/main.phel', $info->filename());
        self::assertSame(';AACA', $info->mappings());
        self::assertSame(2, $info->codeStartLine());
    }

    public function test_returns_none_for_built_output_without_phel_sibling(): void
    {
        $file = $this->dir . '/orphan.php';
        file_put_contents($file, "<?php declare(strict_types=1);\n\$x = 1;\n");
        file_put_contents($file . '.map', ';AACA');

        self::assertEquals(SourceMapInformation::none(), $this->extractor->extractFromFile($file));
    }

    public function test_returns_none_for_plain_php_file(): void
    {
        $file = $this->dir . '/vendor.php';
        file_put_contents($file, "<?php\n\$x = 1;\n");

        self::assertEquals(SourceMapInformation::none(), $this->extractor->extractFromFile($file));
    }

    public function test_returns_none_for_missing_file(): void
    {
        self::assertEquals(
            SourceMapInformation::none(),
            $this->extractor->extractFromFile($this->dir . '/does-not-exist.php'),
        );
    }
}
