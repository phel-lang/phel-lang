<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Formatter;

use Phel;
use Phel\Formatter\Domain\FormatterInterface;
use Phel\Formatter\FormatterFactory;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function array_chunk;
use function array_map;
use function implode;
use function ltrim;
use function range;
use function sprintf;

/**
 * `phel format` over generated data files: one collection literal with
 * thousands of elements, the shape a baked lookup table or asset file has.
 *
 * The rule pipeline walks the parse tree with a zipper whose every step
 * used to copy the sibling arrays, so a literal of n elements cost O(n^2):
 * 16 000 numbers on one line took 28 s and a 486 KB sprite file 95 s
 * (#3218). Two shapes, because the cost of a step and the cost of an edit
 * are different things: `bench_format_wide_literal` is one line the rules
 * only walk, `bench_format_generated_table` is the same elements over many
 * lines, where the unindent and indent rules remove and insert whitespace
 * inside the one big list.
 *
 * @BeforeMethods("setUp")
 */
final class FormatterBench
{
    private const int ELEMENTS = 2000;

    private const int PER_LINE = 16;

    private FormatterInterface $formatter;

    private string $wideLiteral = '';

    private string $generatedTable = '';

    public function setUp(): void
    {
        Phel::bootstrap(__DIR__ . '/../../../../');

        $this->formatter = new FormatterFactory()->createFormatter();
        $numbers = array_map(static fn(int $i): int => $i % 256, range(0, self::ELEMENTS - 1));

        $this->wideLiteral = sprintf("(ns probe.wide)\n\n(def data (php/array %s))\n", implode(' ', $numbers));

        $lines = [];
        foreach (array_chunk($numbers, self::PER_LINE) as $chunk) {
            $lines[] = '   ' . implode(' ', $chunk);
        }

        $this->generatedTable = sprintf("(ns probe.table)\n\n(def data\n  [%s])\n", ltrim(implode("\n", $lines)));
    }

    /**
     * @Revs(3)
     *
     * @Iterations(5)
     */
    public function bench_format_wide_literal(): void
    {
        $this->formatAndTouch($this->wideLiteral);
    }

    /**
     * @Revs(3)
     *
     * @Iterations(5)
     */
    public function bench_format_generated_table(): void
    {
        $this->formatAndTouch($this->generatedTable);
    }

    private function formatAndTouch(string $source): void
    {
        $formatted = $this->formatter->format($source);
        if ($formatted === '') {
            throw new RuntimeException('Formatter returned nothing.');
        }
    }
}
