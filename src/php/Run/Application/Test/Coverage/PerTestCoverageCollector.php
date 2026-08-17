<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use Closure;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;

use function is_string;

/**
 * Attributes raw line coverage to the test that produced it. Installed as
 * `phel.test/*event-hook*`, it sees every reporter event of the run:
 * `:begin-test` starts the driver, `:end-test` stops it and files the lines
 * that were hit under `ns/test-name`. Only lines with hits are kept, so the
 * per-test payload stays small however many files the process has loaded;
 * mapping to `.phel` happens once at the end ({@see CoverageAggregator::attribute()}).
 *
 * @internal
 */
final class PerTestCoverageCollector
{
    public const string PHEL_TEST_NAMESPACE = 'phel.test';

    public const string EVENT_HOOK_VAR = '*event-hook*';

    /** @var array<string, array<string, list<int>>> testId => compiledPhpFile => PHP lines with hits */
    private array $hitLinesByTest = [];

    /**
     * @param Closure(): void                           $start
     * @param Closure(): array<string, array<int, int>> $stop  returns compiledPhpFile => [line => hitCount]
     */
    public function __construct(
        private readonly Closure $start,
        private readonly Closure $stop,
        private readonly string $driverName,
    ) {}

    /**
     * Receives one `phel.test` reporter event; everything but the per-test
     * lifecycle pair is ignored.
     */
    public function __invoke(mixed $event): void
    {
        if (!$event instanceof PersistentMapInterface) {
            return;
        }

        $type = $event->find(Keyword::create('type'));
        if (!$type instanceof Keyword) {
            return;
        }

        if ($type->getName() === 'begin-test') {
            ($this->start)();
            return;
        }

        if ($type->getName() === 'end-test') {
            $this->hitLinesByTest[$this->testId($event)] = $this->hitLines(($this->stop)());
        }
    }

    public static function forDriver(CoverageDriver $driver): self
    {
        return new self($driver->start(...), $driver->stop(...), $driver->name());
    }

    /**
     * Makes this collector the `phel.test/*event-hook*` of the process. The
     * var must already exist, i.e. `phel.test` must be loaded.
     */
    public function install(): void
    {
        Registry::getInstance()
            ->getVar(self::PHEL_TEST_NAMESPACE, self::EVENT_HOOK_VAR)
            ->alterRoot(fn(): self => $this);
    }

    public function driverName(): string
    {
        return $this->driverName;
    }

    /**
     * @return array<string, array<string, list<int>>> testId => compiledPhpFile => PHP lines with hits
     */
    public function hitLinesByTest(): array
    {
        return $this->hitLinesByTest;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $event
     */
    private function testId(PersistentMapInterface $event): string
    {
        $ns = $event->find(Keyword::create('ns'));
        $name = $event->find(Keyword::create('test-name'));

        return (is_string($ns) ? $ns : '') . '/' . (is_string($name) ? $name : '');
    }

    /**
     * @param array<string, array<int, int>> $raw
     *
     * @return array<string, list<int>>
     */
    private function hitLines(array $raw): array
    {
        $out = [];
        foreach ($raw as $phpFile => $hits) {
            $lines = [];
            foreach ($hits as $line => $count) {
                if ($count > 0) {
                    $lines[] = $line;
                }
            }

            if ($lines !== []) {
                $out[$phpFile] = $lines;
            }
        }

        return $out;
    }
}
