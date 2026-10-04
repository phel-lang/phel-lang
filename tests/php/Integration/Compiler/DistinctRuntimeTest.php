<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Closure;
use Generator;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Struct\AbstractPersistentStruct;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use Traversable;

final class DistinctRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    #[DataProvider('associative_shapes')]
    public function test_distinct_consumes_associative_entries_lazily(bool $isStruct): void
    {
        $pulls = 0;
        $iterator = static function () use (&$pulls): Generator {
            foreach (['a' => 1, 'b' => 2, 'c' => 3] as $key => $value) {
                ++$pulls;
                yield Keyword::create($key) => $value;
            }
        };

        if ($isStruct) {
            $source = new class($iterator) extends AbstractPersistentStruct {
                public function __construct(private readonly Closure $iterator)
                {
                    parent::__construct();
                }

                public function getIterator(): Traversable
                {
                    return ($this->iterator)();
                }
            };
        } else {
            $source = $this->createStub(PersistentMapInterface::class);
            $source->method('getIterator')->willReturnCallback($iterator);
        }

        $distinct = Registry::readRoot('phel.core', 'distinct');
        $result = $distinct($source);

        self::assertSame(0, $pulls, 'Constructing distinct must not iterate its source.');

        $first = Registry::readRoot('phel.core', 'first');
        self::assertSame([Keyword::create('a'), 1], $first($result)->toArray());
        self::assertLessThan(3, $pulls, 'Reading the first entry must not drain the source.');
    }

    public static function associative_shapes(): iterable
    {
        yield 'map' => [false];
        yield 'struct' => [true];
    }
}
