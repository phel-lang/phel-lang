<?php

declare(strict_types=1);

namespace PhelTest\Support;

use Phel\Shared\Exceptions\ErrorCodeCatalog;
use Phel\Shared\Exceptions\ErrorCodeExplanation;

use function array_keys;
use function count;
use function dirname;
use function implode;
use function intdiv;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;
use function usort;

/**
 * Renders the pages under `docs/errors/` from {@see ErrorCodeCatalog}.
 *
 * The catalog is the only source. A page is never written by hand, so a code
 * cannot be documented with prose the compiler does not actually print, and a
 * code added to the enum cannot quietly stay undocumented: the catalog gate and
 * this renderer fail together.
 *
 * The split mirrors `PublicApiSurface`: this class renders, `tools/dump-error-docs.php`
 * writes, and `ErrorCodeInventoryTest` compares the committed files against the
 * same render.
 */
final readonly class ErrorDocs
{
    /** The command every generated page names as the way to regenerate it. */
    public const string UPDATE_COMMAND = 'composer error-docs:update';

    private const int RANGE_SIZE = 100;

    /**
     * One entry per hundred-code range, keyed by the hundreds digit of the code.
     *
     * The mapping is the range table in `ErrorCode`, not a list of cases: a new
     * case lands on its page by its number alone.
     *
     * @var array<int, array{slug: string, name: string, intro: string}>
     */
    private const array RANGES = [
        0 => [
            'slug' => 'analyzer',
            'name' => 'Analyzer',
            'intro' => 'The analyzer walks the parsed forms and resolves every symbol, arity and binding. '
                . 'A code in this range means the file parsed and does not make sense.',
        ],
        1 => [
            'slug' => 'parser',
            'name' => 'Parser',
            'intro' => 'The parser turns the token stream into forms. '
                . 'A code in this range means a delimiter or a token sits where no form can be built from it.',
        ],
        2 => [
            'slug' => 'reader',
            'name' => 'Reader',
            'intro' => 'The reader turns forms into the values the analyzer sees, and expands quoting. '
                . 'A code in this range means a quote or an unquote was used where it cannot work.',
        ],
        3 => [
            'slug' => 'lexer',
            'name' => 'Lexer',
            'intro' => 'The lexer turns source text into tokens. '
                . 'A code in this range means the file holds a character or a literal the language does not accept.',
        ],
        4 => [
            'slug' => 'runtime',
            'name' => 'Runtime',
            'intro' => 'These are raised while compiled code runs, not while it compiles. '
                . 'A code in this range means the program was well formed and the values it met were not.',
        ],
    ];

    public function __construct(
        private string $outputDirectory,
    ) {}

    public static function fromRepositoryRoot(string $repositoryRoot): self
    {
        return new self($repositoryRoot . '/docs/errors');
    }

    public static function repositoryRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function outputDirectory(): string
    {
        return $this->outputDirectory;
    }

    /**
     * Every page the catalog describes, in write order: the index first, then one
     * page per non-empty range, ascending. A range with no codes gets no page.
     *
     * @return array<string, string> file name => contents
     */
    public function render(): array
    {
        $grouped = $this->groupedByRange();
        $pages = ['README.md' => $this->renderIndex($grouped)];

        foreach ($grouped as $bucket => $explanations) {
            $range = $this->rangeOf($bucket);
            $pages[$range['slug'] . '.md'] = $this->renderRangePage($bucket, $explanations);
        }

        return $pages;
    }

    /**
     * @param array<int, list<ErrorCodeExplanation>> $grouped
     */
    private function renderIndex(array $grouped): string
    {
        $lines = $this->banner();
        $lines[] = '# Phel error codes';
        $lines[] = '';
        $lines[] = 'Every error Phel reports carries a code such as `PHEL001`. The code names the';
        $lines[] = 'error and never changes, so it is what to search for and what to quote in a bug';
        $lines[] = 'report. `phel explain PHEL001` prints the same explanation in the terminal.';
        $lines[] = '';
        $lines[] = 'The range a code falls in says which phase raised it. Anything below `PHEL400`';
        $lines[] = 'failed before the program ran.';
        $lines[] = '';
        $lines[] = '| Range | Page | Codes |';
        $lines[] = '|---|---|---|';

        foreach ($grouped as $bucket => $explanations) {
            $range = $this->rangeOf($bucket);
            $lines[] = sprintf(
                '| %s | [%s errors](%s.md) | %d |',
                $this->rangeLabel($bucket),
                $range['name'],
                $range['slug'],
                count($explanations),
            );
        }

        $lines[] = '';
        $lines[] = '## Every code';
        $lines[] = '';
        $lines[] = '| Code | Error | Enum case | Phase |';
        $lines[] = '|---|---|---|---|';

        foreach ($grouped as $bucket => $explanations) {
            $range = $this->rangeOf($bucket);

            foreach ($explanations as $explanation) {
                $lines[] = sprintf(
                    '| [%s](%s.md#%s) | %s | `%s` | %s |',
                    $explanation->code->value,
                    $range['slug'],
                    $this->anchorOf($explanation),
                    $this->cell($explanation->title),
                    $explanation->code->name,
                    $range['name'],
                );
            }
        }

        return $this->finish($lines);
    }

    /**
     * @param list<ErrorCodeExplanation> $explanations
     */
    private function renderRangePage(int $bucket, array $explanations): string
    {
        $range = $this->rangeOf($bucket);

        $lines = $this->banner();
        $lines[] = sprintf('# %s errors (%s)', $range['name'], $this->rangeLabel($bucket));
        $lines[] = '';
        $lines[] = $range['intro'];
        $lines[] = '';
        $lines[] = '| Code | Error |';
        $lines[] = '|---|---|';

        foreach ($explanations as $explanation) {
            $lines[] = sprintf(
                '| [%s](#%s) | %s |',
                $explanation->code->value,
                $this->anchorOf($explanation),
                $this->cell($explanation->title),
            );
        }

        foreach ($explanations as $explanation) {
            $lines[] = '';
            $lines[] = sprintf('## %s', $this->headingOf($explanation));
            $lines[] = '';
            $lines[] = sprintf('Enum case: `ErrorCode::%s`', $explanation->code->name);
            $lines[] = '';
            $lines[] = $explanation->summary;

            if (trim($explanation->example) !== '') {
                $lines[] = '';
                $lines[] = '```phel';
                $lines[] = trim($explanation->example, "\n");
                $lines[] = '```';
            }

            $lines[] = '';
            $lines[] = sprintf('**Fix:** %s', $explanation->fix);
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '[All error codes](README.md)';

        return $this->finish($lines);
    }

    /**
     * @return list<string>
     */
    private function banner(): array
    {
        return [
            sprintf('<!-- Generated by `%s`. Do not edit by hand. -->', self::UPDATE_COMMAND),
            '<!-- Source: src/php/Shared/Exceptions/ErrorCodeCatalog.php -->',
            '',
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function finish(array $lines): string
    {
        return implode("\n", $lines) . "\n";
    }

    /**
     * Every catalog entry bucketed by range and sorted by code, so the pages read
     * in numeric order whatever order the catalog happens to declare them in.
     *
     * @return array<int, list<ErrorCodeExplanation>>
     */
    private function groupedByRange(): array
    {
        $grouped = [];

        foreach (ErrorCodeCatalog::all() as $explanation) {
            $grouped[intdiv($this->numberOf($explanation), self::RANGE_SIZE)][] = $explanation;
        }

        ksort($grouped);

        foreach (array_keys($grouped) as $bucket) {
            $entries = $grouped[$bucket];
            usort(
                $entries,
                fn(ErrorCodeExplanation $a, ErrorCodeExplanation $b): int => $this->numberOf($a) <=> $this->numberOf($b),
            );
            $grouped[$bucket] = $entries;
        }

        return $grouped;
    }

    private function numberOf(ErrorCodeExplanation $explanation): int
    {
        return preg_match('/(\d+)$/', $explanation->code->value, $matches) === 1
            ? (int) $matches[1]
            : 0;
    }

    /**
     * A range Phel has not named yet still gets a page rather than a crash: the
     * codes are what a user reads, and they are documented either way.
     *
     * @return array{slug: string, name: string, intro: string}
     */
    private function rangeOf(int $bucket): array
    {
        return self::RANGES[$bucket] ?? [
            'slug' => strtolower($this->rangeLabel($bucket)),
            'name' => $this->rangeLabel($bucket),
            'intro' => 'This range has no phase description yet. Add one to `ErrorDocs::RANGES`.',
        ];
    }

    private function rangeLabel(int $bucket): string
    {
        return sprintf(
            'PHEL%03d-%03d',
            $bucket * self::RANGE_SIZE,
            ($bucket * self::RANGE_SIZE) + self::RANGE_SIZE - 1,
        );
    }

    private function headingOf(ErrorCodeExplanation $explanation): string
    {
        return sprintf('%s: %s', $explanation->code->value, $explanation->title);
    }

    /**
     * The anchor GitHub derives from the section heading: lowercased, punctuation
     * dropped, spaces hyphenated.
     */
    private function anchorOf(ErrorCodeExplanation $explanation): string
    {
        $slug = preg_replace('/[^a-z0-9 -]+/', '', strtolower($this->headingOf($explanation))) ?? '';

        return str_replace(' ', '-', trim($slug));
    }

    /**
     * A pipe in a title would end the table cell it sits in.
     */
    private function cell(string $value): string
    {
        return str_replace('|', '\|', $value);
    }
}
