<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use RuntimeException;

use function sprintf;

/**
 * The realistic-source corpus the front-half compiler benchmarks read.
 *
 * It is a frozen snapshot of `src/phel/core.phel`, and it is frozen because
 * the bench job compares a baseline run against a head run: an input taken
 * from `src/` differs between the two whenever the pull request touches the
 * standard library, so the assertion measures how much the file grew instead
 * of how fast the compiler is. Living under `tests/php/Benchmark` puts it on
 * the side the job pins to the pull request's revision for both runs.
 *
 * The `.phel.txt` extension is load-bearing. The corpus declares `ns
 * phel.core`, so with a bare `.phel` extension the namespace scanner finds a
 * second file claiming that namespace and `(load "core/meta")` starts
 * resolving against this directory. It is benchmark input text, never a
 * namespace to load, and the extension is what says so.
 */
final class CoreCorpus
{
    private const string PATH = __DIR__ . '/core-corpus.phel.txt';

    public static function source(): string
    {
        $source = file_get_contents(self::PATH);
        if ($source === false) {
            throw new RuntimeException(sprintf('Unable to read the benchmark corpus at %s', self::PATH));
        }

        return $source;
    }
}
