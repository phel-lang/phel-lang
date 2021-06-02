<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections\Vector;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\LinkedList\EmptyList;
use Phel\Lang\Collections\LinkedList\PersistentList;
use Phel\Lang\TypeFactory;
use PhelTest\Unit\Lang\Collections\ModuloHasher;
use PhelTest\Unit\Lang\Collections\SimpleEqualizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmptyListTest extends TestCase
{
    public function test_prepend_on_empty_list(): void
    {
        $list = (new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null))->prepend('foo');

        $this->assertTrue($list instanceof PersistentList);
        $this->assertEquals(1, $list->count());
        $this->assertEquals('foo', $list->get(0));
    }

    public function test_can_not_pop_on_emtpy_list(): void
    {
        $this->expectException(RuntimeException::class);

        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $list->pop();
    }

    public function test_count(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals(0, $list->count());
    }

    public function test_can_get_on_emtpy_list(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);

        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $list->get(0);
    }

    public function test_equals_different_type(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertFalse($list->equals([]));
    }

    public function test_equals_same_type(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertTrue($list->equals(new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null)));
    }

    public function test_hash(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals(1, $list->hash());
    }

    public function test_iterator(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);

        $result = [];
        foreach ($list as $index => $value) {
            $result[$index] = $value;
        }
        $this->assertEquals([], $result);
    }

    public function test_first(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertNull($list->first());
    }

    public function test_rest(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals($list, $list->rest());
    }

    public function test_cdr(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertNull($list->cdr());
    }

    public function test_to_array(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals([], $list->toArray());
    }

    public function test_concat_empty_array(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals([], $list->concat([])->toArray());
    }

    public function test_concat_single_entry_array(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertEquals([1], $list->concat([1])->toArray());
    }

    public function test_cons_on_empty_list(): void
    {
        $list = (new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null))->cons('foo');

        $this->assertTrue($list instanceof PersistentList);
        $this->assertEquals(1, $list->count());
        $this->assertEquals('foo', $list->get(0));
    }

    public function test_offset_exists(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertFalse(isset($list[0]));
    }

    public function test_contains(): void
    {
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $this->assertFalse($list->contains(0));
    }

    public function test_offset_get(): void
    {
        $this->expectException(IndexOutOfBoundsException::class);

        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $list[0];
    }

    public function test_add_meta_data(): void
    {
        $meta = TypeFactory::getInstance()->emptyPersistentMap();
        $list = new EmptyList(new ModuloHasher(), new SimpleEqualizer(), null);
        $listWithMeta = $list->withMeta($meta);

        $this->assertEquals(null, $list->getMeta());
        $this->assertEquals($meta, $listWithMeta->getMeta());
    }
}
