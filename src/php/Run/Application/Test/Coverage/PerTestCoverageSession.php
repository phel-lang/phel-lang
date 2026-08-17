<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use Closure;
use Phel\Lang\Registry;

/**
 * Process-wide per-test coverage attribution for hosts that run `phel.test`
 * themselves (the mutation worker): {@see begin()} installs a
 * {@see PerTestCoverageCollector} as `phel.test/*event-hook*` when a driver
 * is available, {@see testsByLine()} maps what it collected so far to
 * project `.phel` lines, {@see end()} removes the hook. One collector per
 * process, so a facade that is instantiated per call still talks to the
 * same run.
 *
 * @internal
 */
final class PerTestCoverageSession
{
    private static ?PerTestCoverageCollector $collector = null;

    /**
     * @param Closure(): ?CoverageDriver                                                      $detectDriver
     * @param Closure(array<string, array<string, list<int>>>, string): PerTestCoverageReport $attribute    hits by test + driver name to a report
     */
    public function __construct(
        private readonly Closure $detectDriver,
        private readonly Closure $attribute,
    ) {}

    /**
     * @return string|null the driver name, or null when no driver is available
     */
    public function begin(): ?string
    {
        if (self::$collector instanceof PerTestCoverageCollector) {
            return self::$collector->driverName();
        }

        $driver = ($this->detectDriver)();
        if (!$driver instanceof CoverageDriver) {
            return null;
        }

        self::$collector = PerTestCoverageCollector::forDriver($driver);
        self::$collector->install();

        return $driver->name();
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    public function testsByLine(): array
    {
        if (!self::$collector instanceof PerTestCoverageCollector) {
            return [];
        }

        return ($this->attribute)(self::$collector->hitLinesByTest(), self::$collector->driverName())->testsByLine();
    }

    public function end(): void
    {
        if (!self::$collector instanceof PerTestCoverageCollector) {
            return;
        }

        Registry::getInstance()
            ->getVar(PerTestCoverageCollector::PHEL_TEST_NAMESPACE, PerTestCoverageCollector::EVENT_HOOK_VAR)
            ->alterRoot(static fn(): null => null);
        self::$collector = null;
    }
}
