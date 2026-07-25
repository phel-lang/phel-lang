<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function in_array;
use function sprintf;

/**
 * Keeps the test tree's own coupling in check.
 *
 * The production graph cannot see any of this, yet shared test helpers erode
 * architecture just as effectively: a fixture that two unrelated modules' suites
 * both reach for silently welds them together, and a test that imports a
 * *different* module's test namespace makes the two suites unrunnable apart.
 *
 * The rule is that cross-namespace test imports may only target `PhelTest\Support`
 * (or a suite's own `Util` helpers) — never another module's test namespace, and
 * never across suites. Anything worth sharing is support code and belongs in
 * `tests/php/Support`.
 */
final class TestSuiteDependencyTest extends TestCase
{
    use ScansPhpSourcesTrait;

    /**
     * Namespace roots under `PhelTest\` that exist to be shared. Everything else
     * is a suite and owns its own fixtures.
     *
     * @var list<string>
     */
    private const array SHARED_ROOTS = ['Support'];

    /**
     * Suite-local helper packages a suite may import across its own module
     * directories, e.g. `PhelTest\Integration\Util\*` from
     * `PhelTest\Integration\Build\*`. Keyed by suite.
     *
     * @var array<string, list<string>>
     */
    private const array SUITE_LOCAL_HELPERS = [
        'Integration' => ['Util'],
    ];

    public function test_tests_only_share_code_through_the_support_namespace(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn('tests/php') as $relative => $contents) {
            $owner = $this->namespaceSegmentsOf($contents);
            if ($owner === null) {
                continue;
            }

            [$suite, $module] = $owner;

            foreach ($this->importsUnder($contents, 'PhelTest') as [$line, $fqn]) {
                $segments = explode('\\', $fqn);
                $targetSuite = $segments[1] ?? '';
                $targetModule = $segments[2] ?? '';

                if ($targetSuite === $suite && $targetModule === $module) {
                    continue;
                }

                if ($this->isSharedOrLocalHelper($suite, $targetSuite, $targetModule)) {
                    continue;
                }

                $violations[] = sprintf('tests/php/%s:%d imports %s', $relative, $line, $fqn);
            }
        }

        self::assertSame(
            [],
            $violations,
            "A test imports another suite's or module's test namespace.\n"
            . "Move whatever is being reused into tests/php/Support (shared fixtures and traits),\n"
            . 'so neither suite owns code the other depends on.',
        );
    }

    /**
     * `Support` must stay a leaf: if shared support imports a suite, the sharing
     * is inverted and the cycle is back, just one level down.
     */
    public function test_shared_support_does_not_depend_on_any_suite(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn('tests/php/Support') as $relative => $contents) {
            foreach ($this->importsUnder($contents, 'PhelTest') as [$line, $fqn]) {
                if ($this->isUnderSharedRoot($fqn)) {
                    continue;
                }

                $violations[] = sprintf('tests/php/Support/%s:%d imports %s', $relative, $line, $fqn);
            }
        }

        self::assertSame([], $violations, 'tests/php/Support must not depend on a test suite.');
    }

    private function isSharedOrLocalHelper(string $suite, string $targetSuite, string $targetModule): bool
    {
        if (in_array($targetSuite, self::SHARED_ROOTS, true)) {
            return true;
        }

        return $targetSuite === $suite
            && in_array($targetModule, self::SUITE_LOCAL_HELPERS[$suite] ?? [], true);
    }

    private function isUnderSharedRoot(string $fqn): bool
    {
        $segments = explode('\\', $fqn);

        return in_array($segments[1] ?? '', self::SHARED_ROOTS, true);
    }

    /**
     * @return array{string, string}|null [suite, module]
     */
    private function namespaceSegmentsOf(string $contents): ?array
    {
        if (preg_match('/^namespace\s+(PhelTest(?:\\\\\w+)*);/m', $contents, $matches) !== 1) {
            return null;
        }

        $segments = explode('\\', $matches[1]);

        return [$segments[1] ?? '', $segments[2] ?? ''];
    }
}
