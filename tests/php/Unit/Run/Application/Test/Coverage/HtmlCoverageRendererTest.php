<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test\Coverage;

use Override;
use Phel\Run\Application\Test\Coverage\CoverageFile;
use Phel\Run\Application\Test\Coverage\CoverageReport;
use Phel\Run\Application\Test\Coverage\HtmlCoverageRenderer;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function escapeshellarg;
use function exec;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class HtmlCoverageRendererTest extends TestCase
{
    private string $sourceDir;

    #[Override]
    protected function setUp(): void
    {
        $this->sourceDir = sys_get_temp_dir() . '/phel-html-coverage-' . bin2hex(random_bytes(8));
        mkdir($this->sourceDir, 0o755, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->sourceDir));
    }

    public function test_index_lists_files_with_percentages_and_links(): void
    {
        $calc = $this->writeSource('calc.phel', "(ns app.calc)\n(defn add [a b]\n  (+ a b))\n");
        $util = $this->writeSource('util.phel', "(ns app.util)\n(def answer 42)\n");
        $renderer = new HtmlCoverageRenderer();

        $pages = $renderer->render(new CoverageReport([
            new CoverageFile($calc, [2 => true, 3 => false]),
            new CoverageFile($util, [2 => true]),
        ], 'pcov'));

        $index = $pages['index.html'];
        self::assertStringContainsString('calc.phel', $index);
        self::assertStringContainsString('util.phel', $index);
        self::assertStringContainsString('50.0%', $index); // calc: 1 of 2
        self::assertStringContainsString('100.0%', $index); // util: 1 of 1
        self::assertStringContainsString('66.7%', $index); // total: 2 of 3
        self::assertStringContainsString('pcov', $index);
        self::assertStringContainsString('href="' . $renderer->pageName($calc) . '"', $index);
        self::assertArrayHasKey($renderer->pageName($calc), $pages);
        self::assertArrayHasKey($renderer->pageName($util), $pages);
    }

    public function test_file_page_marks_covered_uncovered_and_neutral_lines(): void
    {
        $calc = $this->writeSource('calc.phel', "(ns app.calc)\n(defn add [a b]\n  (+ a b))\n");
        $renderer = new HtmlCoverageRenderer();

        $pages = $renderer->render(new CoverageReport([
            new CoverageFile($calc, [2 => true, 3 => false]),
        ], 'pcov'));
        $filePage = $pages[$renderer->pageName($calc)];

        self::assertStringContainsString(
            '<tr class="neutral"><td class="ln">1</td><td class="code">(ns app.calc)</td></tr>',
            $filePage,
        );
        self::assertStringContainsString(
            '<tr class="covered"><td class="ln">2</td><td class="code">(defn add [a b]</td></tr>',
            $filePage,
        );
        self::assertStringContainsString(
            '<tr class="uncovered"><td class="ln">3</td><td class="code">  (+ a b))</td></tr>',
            $filePage,
        );
        self::assertStringContainsString('<a href="index.html">', $filePage);
    }

    public function test_file_page_escapes_html_in_source(): void
    {
        $file = $this->writeSource('markup.phel', "(def snippet \"<b>bold & bad</b>\")\n");
        $renderer = new HtmlCoverageRenderer();

        $pages = $renderer->render(new CoverageReport([
            new CoverageFile($file, [1 => true]),
        ], 'pcov'));
        $filePage = $pages[$renderer->pageName($file)];

        self::assertStringNotContainsString('<b>', $filePage);
        self::assertStringContainsString('&lt;b&gt;bold &amp; bad&lt;/b&gt;', $filePage);
    }

    public function test_output_is_self_contained(): void
    {
        $calc = $this->writeSource('calc.phel', "(ns app.calc)\n(defn add [a b]\n  (+ a b))\n");

        $pages = new HtmlCoverageRenderer()->render(new CoverageReport([
            new CoverageFile($calc, [2 => true, 3 => false]),
        ], 'pcov'));

        foreach ($pages as $pageName => $html) {
            self::assertStringNotContainsString('http://', $html, $pageName . ' must not reference external resources');
            self::assertStringNotContainsString('https://', $html, $pageName . ' must not reference external resources');
            self::assertStringContainsString('<style>', $html, $pageName . ' must inline its CSS');
        }
    }

    public function test_page_names_disambiguate_equal_basenames(): void
    {
        $renderer = new HtmlCoverageRenderer();

        self::assertNotSame(
            $renderer->pageName('/proj/src/calc.phel'),
            $renderer->pageName('/proj/src/nested/calc.phel'),
        );
        self::assertStringStartsWith('calc.phel.', $renderer->pageName('/proj/src/calc.phel'));
    }

    public function test_index_when_no_files_were_executed(): void
    {
        $pages = new HtmlCoverageRenderer()->render(new CoverageReport([], 'xdebug'));

        self::assertCount(1, $pages);
        self::assertStringContainsString('No project source files were executed.', $pages['index.html']);
    }

    public function test_file_page_when_source_is_not_readable(): void
    {
        $missing = $this->sourceDir . '/deleted.phel';
        $renderer = new HtmlCoverageRenderer();

        $pages = $renderer->render(new CoverageReport([
            new CoverageFile($missing, [1 => true]),
        ], 'pcov'));

        self::assertStringContainsString('Source file is not readable.', $pages[$renderer->pageName($missing)]);
    }

    private function writeSource(string $basename, string $code): string
    {
        $path = $this->sourceDir . '/' . $basename;
        file_put_contents($path, $code);

        return $path;
    }
}
