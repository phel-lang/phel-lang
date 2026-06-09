<?php

declare(strict_types=1);

namespace PhelTest\Integration\Command\Domain\Extractor;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractor;
use Phel\Command\Infrastructure\SourceMapExtractor;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file;
use function glob;
use function mkdir;
use function rmdir;
use function sprintf;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * End-to-end check that exception positions inside `phel build` output can be
 * mapped back to the Phel source through the sibling `<file>.php.map` and
 * `<file>.phel` artifacts the build writes next to each compiled file.
 */
final class BuiltOutputSourceMapTest extends TestCase
{
    private string $outDir = '';

    protected function tearDown(): void
    {
        if ($this->outDir !== '') {
            array_map(unlink(...), glob($this->outDir . '/*') ?: []);
            rmdir($this->outDir);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_built_output_position_maps_back_to_sibling_phel_source(): void
    {
        Phel::bootstrap(__DIR__);

        $srcFile = __DIR__ . '/Fixtures/main.phel';
        $this->outDir = sys_get_temp_dir() . '/phel-built-source-map-' . uniqid();
        mkdir($this->outDir);
        $outDir = $this->outDir;
        $dest = $outDir . '/main.php';

        new BuildFacade()->compileFile($srcFile, $dest);

        self::assertFileExists($dest . '.map');
        self::assertFileExists($outDir . '/main.phel');

        // The compiled `(php/+ a b)` expression is the only generated line
        // containing a `+` operator; a runtime error there would report this line.
        $generatedLine = $this->findLineContaining($dest, ' + ');
        $expectedPhelLine = $this->findLineContaining($srcFile, 'php/+');

        $position = new FilePositionExtractor(new SourceMapExtractor())
            ->getOriginal($dest, $generatedLine);

        self::assertSame($outDir . '/main.phel', $position->filename());
        self::assertSame($expectedPhelLine, $position->line());
    }

    private function findLineContaining(string $file, string $needle): int
    {
        foreach ((array) file($file) as $index => $line) {
            if (str_contains((string) $line, $needle)) {
                return $index + 1;
            }
        }

        self::fail(sprintf("No line containing '%s' in %s", $needle, $file));
    }
}
