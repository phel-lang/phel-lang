<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain;

use Phel\Shared\ScalarCoercion;

use function is_array;
use function is_string;

/**
 * The per-test coverage attribution as it travels between the parent and
 * the workers: `file => line => list of "ns/test-name"`, decoded from the
 * JSON frame back into typed keys (JSON turns the line numbers into
 * strings, PHP arrays turn them back only when the key is numeric).
 *
 * @internal
 */
final class TestsByLine
{
    /**
     * @param array<mixed, mixed> $raw
     *
     * @return array<string, array<int, list<string>>>
     */
    public static function fromWire(array $raw): array
    {
        $out = [];
        foreach ($raw as $file => $lines) {
            if (!is_string($file)) {
                continue;
            }

            if (!is_array($lines)) {
                continue;
            }

            foreach ($lines as $line => $tests) {
                if (!is_array($tests)) {
                    continue;
                }

                $out[$file][(int) $line] = ScalarCoercion::toStringList($tests);
            }
        }

        return $out;
    }
}
