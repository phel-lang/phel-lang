<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Which `.phel` lines each test executed, and the inverse: which tests
 * executed each line. Both indexes are shipped in one JSON document so a
 * consumer (test impact analysis, a mutation runner picking the tests
 * that cover a mutated line) never has to invert one into the other.
 *
 * @internal
 */
final readonly class PerTestCoverageReport
{
    /**
     * @param array<string, array<string, list<int>>> $linesByTest testId => phelFile => sorted lines
     * @param array<string, array<int, list<string>>> $testsByLine phelFile => phelLine => sorted testIds
     */
    public function __construct(
        private array $linesByTest,
        private array $testsByLine,
        private string $driver,
    ) {}

    /**
     * @return array<string, array<string, list<int>>>
     */
    public function linesByTest(): array
    {
        return $this->linesByTest;
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    public function testsByLine(): array
    {
        return $this->testsByLine;
    }

    public function driverName(): string
    {
        return $this->driver;
    }

    /**
     * Compact on purpose: on a 3000-test project the pretty-printed document
     * is three times the size, and it is read by tools, not people.
     */
    public function toJson(): string
    {
        $tests = [];
        foreach ($this->linesByTest as $testId => $files) {
            $tests[$testId] = (object) $files;
        }

        $lines = [];
        foreach ($this->testsByLine as $file => $byLine) {
            $lines[$file] = (object) $byLine;
        }

        return json_encode(
            [
                'driver' => $this->driver,
                'tests' => (object) $tests,
                'lines' => (object) $lines,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ) . "\n";
    }
}
