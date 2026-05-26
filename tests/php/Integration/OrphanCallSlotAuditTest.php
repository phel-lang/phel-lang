<?php

declare(strict_types=1);

namespace PhelTest\Integration;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_keys;
use function count;
use function file_get_contents;
use function preg_match_all;
use function preg_split;
use function realpath;
use function sprintf;
use function str_ends_with;

/**
 * Walks every `--PHP--` block under `tests/php/Integration/Fixtures` and
 * asserts that every `static $__phel_call_N` / `static $__phel_const_N`
 * slot reserved in the body is actually written by an `??=`
 * initialisation somewhere in the same body. An orphan slot is a
 * signal that the `BodyConstantScanner` reserved a slot for a call
 * that a specialiser later consumed without using it — invisible at
 * runtime, but bloats the bytecode and the OPcache footprint.
 */
final class OrphanCallSlotAuditTest extends TestCase
{
    public function test_fixtures_contain_no_orphan_phel_call_or_const_slots(): void
    {
        $fixturesDir = realpath(__DIR__ . '/Fixtures');
        self::assertIsString($fixturesDir);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixturesDir),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $orphans = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!str_ends_with($file->getRealPath(), '.test')) {
                continue;
            }

            $content = (string) file_get_contents($file->getRealPath());
            $parts = preg_split('/^--PHP--\s*$/m', $content, 2);
            if ($parts === false) {
                continue;
            }

            if (count($parts) !== 2) {
                continue;
            }

            $php = $parts[1];
            $declared = $this->collectDeclaredSlots($php);
            $used = $this->collectUsedSlots($php);

            foreach (array_keys($declared) as $slot) {
                if (!isset($used[$slot])) {
                    $orphans[] = sprintf(
                        '%s: %s declared but never written',
                        str_replace($fixturesDir . '/', '', $file->getRealPath()),
                        $slot,
                    );
                }
            }
        }

        self::assertSame(
            [],
            $orphans,
            "Orphan slot declarations found in fixtures:\n  " . implode("\n  ", $orphans),
        );
    }

    /**
     * @return array<string, true>
     */
    private function collectDeclaredSlots(string $php): array
    {
        $declared = [];
        if (preg_match_all('/static\s+([^;]+);/', $php, $matches) === false) {
            return [];
        }

        foreach ($matches[1] as $declList) {
            if (preg_match_all('/\$(?:__phel_call_\d+|__phel_const_\d+|__phel_kw_\d+)/', $declList, $slots) === false) {
                continue;
            }

            foreach ($slots[0] as $name) {
                $declared[$name] = true;
            }
        }

        return $declared;
    }

    /**
     * @return array<string, true>
     */
    private function collectUsedSlots(string $php): array
    {
        $used = [];
        if (preg_match_all('/\$(__phel_call_\d+|__phel_const_\d+|__phel_kw_\d+)\s*\?\?=/', $php, $matches) === false) {
            return [];
        }

        foreach ($matches[1] as $name) {
            $used['$' . $name] = true;
        }

        return $used;
    }
}
