<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Domain\Inspection;

use Phel;
use Phel\Lang\Collections\LazySeq\LazySeq;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use Phel\Nrepl\Domain\Inspection\ValueInspection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use stdClass;

use WeakReference;

use function strlen;

final class ValueInspectionTest extends TestCase
{
    public function test_it_pages_mixed_values_without_realizing_the_sequence_or_printing_objects(): void
    {
        $realized = 0;
        $generator = (static function () use (&$realized) {
            for ($i = 0; ; ++$i) {
                ++$realized;
                yield $i;
            }
        })();
        $lazy = LazySeq::fromGenerator(new Hasher(), new Equalizer(), $generator);
        $object = new class() {
            public int $calls = 0;

            public string $visible = 'hello';

            public string $backed = 'raw' {
                get {
                    ++$this->calls; return $this->backed . 'hook';
                }
            }

            public function __get(string $name): mixed
            {
                ++$this->calls;
                return null;
            }

            public string $computed {
                get {
                    ++$this->calls; return 'computed';
                }
            }

            public function __debugInfo(): array
            {
                ++$this->calls;
                return [];
            }

            public function __toString(): string
            {
                ++$this->calls;
                return 'printed';
            }
        };
        $inspection = new ValueInspection(Phel::map('vector', Phel::vector(range(0, 9999)), 'lazy', $lazy, 'object', $object));
        $root = $inspection->root();
        self::assertSame('collection', $root['kind']);
        self::assertSame(0, $realized);
        $page = $inspection->page($root['handle'], 0, 3);
        self::assertCount(3, $page['children']);
        self::assertSame(0, $realized);
        $children = array_column($page['children'], null, 'label');
        $lazyPage = $inspection->page($children['lazy']['handle'], 0, 2);
        self::assertCount(2, $lazyPage['children']);
        self::assertLessThanOrEqual(3, $realized);
        $before = $realized;
        self::assertSame($lazyPage, $inspection->page($children['lazy']['handle'], 0, 2));
        self::assertSame($before, $realized);
        $objectPage = $inspection->page($children['object']['handle'], 0, 32);
        self::assertSame(['calls', 'visible', 'backed'], array_column($objectPage['children'], 'label'));
        self::assertSame('raw', $objectPage['children'][2]['preview']);
        self::assertSame(0, $object->calls);
    }

    public function test_it_reuses_object_handles_and_bounds_array_cycles_and_items(): void
    {
        $object = new stdClass();
        $object->self = $object;

        $inspection = new ValueInspection($object);
        $root = $inspection->root();
        $child = $inspection->page($root['handle'], 0, 1)['children'][0];
        self::assertSame($root['handle'], $child['handle']);
        self::assertTrue($child['reference']);
        $array = [];
        $array['self'] = &$array;
        $inspection = new ValueInspection($array);
        $node = $inspection->root();
        for ($i = 0; $i < ValueInspection::MAX_DEPTH; ++$i) {
            $node = $inspection->page($node['handle'], 0, 1)['children'][0];
        }

        self::assertFalse($node['expandable']);
        self::assertSame('depth', $node['reason']);
        $inspection = new ValueInspection(range(0, 9999));
        $root = $inspection->root();
        $offset = 0;
        do {
            $page = $inspection->page($root['handle'], $offset, 32);
            $offset = $page['next-offset'];
        } while ($offset !== -1);

        self::assertTrue($page['truncated']);
        self::assertSame('items', $page['reason']);
    }

    public function test_release_drops_values_and_invalidates_handles(): void
    {
        $object = new stdClass();
        $weak = WeakReference::create($object);
        $inspection = new ValueInspection($object);
        $handle = $inspection->root()['handle'];
        unset($object);
        $inspection->release();
        self::assertNull($weak->get());
        $this->expectException(RuntimeException::class);
        $inspection->page($handle, 0, 1);
    }

    public function test_lazy_objects_and_failed_sequences_do_not_escape_inspection(): void
    {
        $calls = 0;
        $reflection = new ReflectionClass(InspectionLazyObject::class);
        $object = $reflection->newLazyGhost(static function () use (&$calls): void {
            ++$calls;
        });
        $inspection = new ValueInspection($object);
        self::assertSame([], $inspection->page($inspection->id(), 0, 32)['children']);
        self::assertSame(0, $calls);
        $lazy = new LazySeq(new Hasher(), new Equalizer(), static function (): never {
            throw new RuntimeException('broken');
        });
        $inspection = new ValueInspection($lazy);
        $page = $inspection->page($inspection->id(), 0, 2);
        self::assertTrue($page['truncated']);
        self::assertSame('unavailable', $page['reason']);
        self::assertSame(-1, $page['next-offset']);
    }

    public function test_offsets_cannot_skip_unvisited_entries(): void
    {
        $inspection = new ValueInspection([1, 2, 3]);
        $this->expectException(RuntimeException::class);
        $inspection->page($inspection->id(), 2, 1);
    }

    public function test_resources_have_bounded_metadata_and_previews_are_bounded(): void
    {
        $resource = fopen('php://memory', 'r+');
        try {
            $root = new ValueInspection($resource)->root();
            self::assertSame('resource', $root['kind']);
            self::assertSame('stream', $root['resource-type']);
            self::assertFalse($root['expandable']);
            self::assertLessThanOrEqual(ValueInspection::PREVIEW_BYTES, strlen(new ValueInspection(str_repeat('x', 10000))->root()['preview']));
        } finally {
            fclose($resource);
        }
    }
}

final class InspectionLazyObject
{
    public int $value;
}
