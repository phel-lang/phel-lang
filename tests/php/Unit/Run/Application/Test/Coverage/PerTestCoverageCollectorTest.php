<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test\Coverage;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Run\Application\Test\Coverage\PerTestCoverageCollector;
use PHPUnit\Framework\TestCase;

final class PerTestCoverageCollectorTest extends TestCase
{
    public function test_files_the_hit_lines_of_each_test_between_begin_and_end(): void
    {
        $started = 0;
        $window = [];
        $collector = new PerTestCoverageCollector(
            static function () use (&$started): void {
                ++$started;
            },
            static function () use (&$window): array {
                return $window;
            },
            'fake',
        );

        $window = ['/cache/calc.php' => [10 => 1, 11 => 0, 12 => 3], '/cache/lib.php' => [5 => 0]];
        $collector($this->event('begin-test', 'app.calc-test', 'add-works'));
        $collector($this->event('binary', 'app.calc-test', 'add-works'));
        $collector($this->event('end-test', 'app.calc-test', 'add-works'));

        $window = [];
        $collector($this->event('begin-test', 'app.calc-test', 'nothing'));
        $collector($this->event('end-test', 'app.calc-test', 'nothing'));

        self::assertSame(2, $started, 'the driver starts once per test');
        self::assertSame(
            [
                'app.calc-test/add-works' => ['/cache/calc.php' => [10, 12]],
                'app.calc-test/nothing' => [],
            ],
            $collector->hitLinesByTest(),
        );
    }

    public function test_ignores_events_that_are_not_maps_or_carry_no_type(): void
    {
        $collector = new PerTestCoverageCollector(
            static function (): never {
                self::fail('must not start');
            },
            static fn(): array => [],
            'fake',
        );

        $collector('begin-test');
        $collector(TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('ns'), 'x'));

        self::assertSame([], $collector->hitLinesByTest());
    }

    private function event(string $type, string $ns, string $testName): PersistentMapInterface
    {
        return TypeFactory::getInstance()->persistentMapFromKVs(
            Keyword::create('type'),
            Keyword::create($type),
            Keyword::create('ns'),
            $ns,
            Keyword::create('test-name'),
            $testName,
        );
    }
}
