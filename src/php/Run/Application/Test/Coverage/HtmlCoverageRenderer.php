<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use function basename;
use function count;
use function explode;
use function file_get_contents;
use function htmlspecialchars;
use function implode;
use function is_file;
use function preg_replace;
use function sha1;
use function sprintf;
use function str_ends_with;
use function substr;

use const ENT_QUOTES;

/**
 * Renders a {@see CoverageReport} as a self-contained static HTML report:
 * an index page with per-file percentages plus one page per source file with
 * line-colored code. Inline CSS only, so the output works offline and as a
 * CI artifact.
 */
final readonly class HtmlCoverageRenderer
{
    private const string CSS = <<<'CSS'
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 2rem auto; max-width: 60rem; padding: 0 1rem; color: #1f2328; }
        h1 { font-size: 1.4rem; }
        h1 code { font-size: 1.1rem; }
        a { color: #0969da; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table.summary { border-collapse: collapse; width: 100%; }
        table.summary th, table.summary td { border: 1px solid #d1d9e0; padding: 0.4rem 0.6rem; text-align: left; }
        table.summary td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.summary tr.total td { font-weight: 600; background: #f6f8fa; }
        .bar { background: #eceff2; border-radius: 3px; width: 8rem; height: 0.6rem; display: inline-block; vertical-align: middle; margin-right: 0.5rem; }
        .bar span { background: #1a7f37; border-radius: 3px; height: 100%; display: block; }
        table.source { border-collapse: collapse; width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.85rem; }
        table.source td { padding: 0 0.6rem; white-space: pre-wrap; }
        table.source td.ln { text-align: right; color: #59636e; user-select: none; border-right: 1px solid #d1d9e0; width: 1%; }
        tr.covered td { background: #dafbe1; }
        tr.covered td.ln { background: #aceebb; }
        tr.uncovered td { background: #ffebe9; }
        tr.uncovered td.ln { background: #ffcecb; }
        tr.neutral td.code { color: #8b949e; }
        .meta { color: #59636e; font-size: 0.85rem; }
        CSS;

    /**
     * @return array<string, string> page filename => full HTML document
     */
    public function render(CoverageReport $report): array
    {
        $pages = ['index.html' => $this->renderIndex($report)];
        foreach ($report->files() as $file) {
            $pages[$this->pageName($file->filename)] = $this->renderFilePage($file);
        }

        return $pages;
    }

    public function pageName(string $filename): string
    {
        $safeBasename = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($filename));

        // The hash disambiguates equal basenames from different directories.
        return sprintf('%s.%s.html', $safeBasename, substr(sha1($filename), 0, 8));
    }

    private function renderIndex(CoverageReport $report): string
    {
        if ($report->files() === []) {
            $body = '<p>No project source files were executed.</p>';

            return $this->document('Phel Coverage', $report, $body);
        }

        $rows = [];
        foreach ($report->files() as $file) {
            $rows[] = sprintf(
                '<tr><td><a href="%s">%s</a></td><td class="num">%s %.1f%%</td><td class="num">%d/%d</td></tr>',
                $this->escape($this->pageName($file->filename)),
                $this->escape($file->filename),
                $this->coverageBar($file->percentage()),
                $file->percentage(),
                $file->coveredCount(),
                $file->coverableCount(),
            );
        }

        $rows[] = sprintf(
            '<tr class="total"><td>Total</td><td class="num">%s %.1f%%</td><td class="num">%d/%d</td></tr>',
            $this->coverageBar($report->totalPercentage()),
            $report->totalPercentage(),
            $report->totalCovered(),
            $report->totalCoverable(),
        );

        $body = '<table class="summary"><thead><tr><th>File</th><th>Coverage</th><th>Lines</th></tr></thead><tbody>'
            . implode('', $rows)
            . '</tbody></table>';

        return $this->document('Phel Coverage', $report, $body);
    }

    private function renderFilePage(CoverageFile $file): string
    {
        $title = sprintf('%s — Phel Coverage', $file->filename);
        $header = sprintf(
            '<p class="meta"><a href="index.html">&larr; Index</a> &middot; %s %.1f%% &middot; %d/%d lines</p>',
            $this->coverageBar($file->percentage()),
            $file->percentage(),
            $file->coveredCount(),
            $file->coverableCount(),
        );

        $source = is_file($file->filename) ? file_get_contents($file->filename) : false;
        if ($source === false) {
            return $this->documentWithHeading(
                $title,
                $file->filename,
                $header . '<p>Source file is not readable.</p>',
            );
        }

        if (str_ends_with($source, "\n")) {
            $source = substr($source, 0, -1);
        }

        $hits = $file->lineHits();
        $rows = [];
        foreach (explode("\n", $source) as $index => $line) {
            $lineNumber = $index + 1;
            $class = 'neutral';
            if (isset($hits[$lineNumber])) {
                $class = $hits[$lineNumber] ? 'covered' : 'uncovered';
            }

            $rows[] = sprintf(
                '<tr class="%s"><td class="ln">%d</td><td class="code">%s</td></tr>',
                $class,
                $lineNumber,
                $this->escape($line),
            );
        }

        $body = $header . '<table class="source"><tbody>' . implode('', $rows) . '</tbody></table>';

        return $this->documentWithHeading($title, $file->filename, $body);
    }

    private function document(string $title, CoverageReport $report, string $body): string
    {
        $heading = sprintf(
            '<h1>Phel Coverage <span class="meta">(%s, %d files)</span></h1>',
            $this->escape($report->driverName()),
            count($report->files()),
        );

        return $this->htmlDocument($title, $heading . $body);
    }

    private function documentWithHeading(string $title, string $filename, string $body): string
    {
        $heading = sprintf('<h1><code>%s</code></h1>', $this->escape($filename));

        return $this->htmlDocument($title, $heading . $body);
    }

    private function htmlDocument(string $title, string $body): string
    {
        return sprintf(
            "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . "<title>%s</title>\n<style>\n%s\n</style>\n</head>\n<body>\n%s\n</body>\n</html>\n",
            $this->escape($title),
            self::CSS,
            $body,
        );
    }

    private function coverageBar(float $percentage): string
    {
        return sprintf('<span class="bar"><span style="width:%.1f%%"></span></span>', $percentage);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
