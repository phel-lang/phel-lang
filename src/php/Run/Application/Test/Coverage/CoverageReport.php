<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use function basename;
use function htmlspecialchars;
use function implode;
use function max;
use function sprintf;
use function str_pad;
use function strlen;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Project-wide coverage built from per-file {@see CoverageFile} results, with
 * text and Clover-XML renderings.
 */
final readonly class CoverageReport
{
    /**
     * @param list<CoverageFile> $files
     */
    public function __construct(
        private array $files,
        private string $driver,
    ) {}

    public function totalCoverable(): int
    {
        $total = 0;
        foreach ($this->files as $file) {
            $total += $file->coverableCount();
        }

        return $total;
    }

    public function totalCovered(): int
    {
        $total = 0;
        foreach ($this->files as $file) {
            $total += $file->coveredCount();
        }

        return $total;
    }

    public function totalPercentage(): float
    {
        $coverable = $this->totalCoverable();
        if ($coverable === 0) {
            return 100.0;
        }

        return (float) $this->totalCovered() / (float) $coverable * 100.0;
    }

    /**
     * @return list<CoverageFile>
     */
    public function files(): array
    {
        return $this->files;
    }

    public function toText(): string
    {
        if ($this->files === []) {
            return "Coverage: no project source files were executed.\n";
        }

        $nameWidth = 4;
        foreach ($this->files as $file) {
            $nameWidth = max($nameWidth, strlen(basename($file->filename)));
        }

        $lines = [];
        $lines[] = sprintf('Coverage (%s)', $this->driver);
        $lines[] = str_pad('', $nameWidth + 28, '=');
        $lines[] = sprintf('%s  %8s  %s', str_pad('File', $nameWidth), 'Lines', 'Covered');

        foreach ($this->files as $file) {
            $lines[] = sprintf(
                '%s  %7.1f%%  %d/%d',
                str_pad(basename($file->filename), $nameWidth),
                $file->percentage(),
                $file->coveredCount(),
                $file->coverableCount(),
            );
        }

        $lines[] = str_pad('', $nameWidth + 28, '-');
        $lines[] = sprintf(
            '%s  %7.1f%%  %d/%d',
            str_pad('Total', $nameWidth),
            $this->totalPercentage(),
            $this->totalCovered(),
            $this->totalCoverable(),
        );

        return implode("\n", $lines) . "\n";
    }

    /**
     * Minimal Clover XML consumable by Codecov and similar tools.
     */
    public function toClover(int $timestamp): string
    {
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = sprintf('<coverage generated="%d">', $timestamp);
        $lines[] = sprintf('  <project timestamp="%d">', $timestamp);

        foreach ($this->files as $file) {
            $lines[] = sprintf('    <file name="%s">', $this->escape($file->filename));
            foreach ($file->lineHits() as $line => $covered) {
                $lines[] = sprintf(
                    '      <line num="%d" type="stmt" count="%d"/>',
                    $line,
                    $covered ? 1 : 0,
                );
            }

            $lines[] = sprintf(
                '      <metrics statements="%d" coveredstatements="%d"/>',
                $file->coverableCount(),
                $file->coveredCount(),
            );
            $lines[] = '    </file>';
        }

        $lines[] = sprintf(
            '    <metrics statements="%d" coveredstatements="%d"/>',
            $this->totalCoverable(),
            $this->totalCovered(),
        );
        $lines[] = '  </project>';
        $lines[] = '</coverage>';

        return implode("\n", $lines) . "\n";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }
}
