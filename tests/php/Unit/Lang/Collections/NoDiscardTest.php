<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Collections;

use Phel\Lang\Collections\LinkedList\PersistentList;
use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Vector\PersistentVector;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_WARNING;

/**
 * The persistent collections return a new instance from every builder, so a
 * discarded call is a silent no-op. `#[NoDiscard]` turns that into a warning.
 *
 * The attribute is NOT inherited: PHP only warns when the resolved *concrete*
 * method carries it, which is why the implementations are annotated and not
 * only the interfaces. These tests exist to catch an implementation losing the
 * attribute while its interface keeps it, which no type check would notice.
 */
final class NoDiscardTest extends TestCase
{
    /** @var list<string> */
    private array $warnings = [];

    protected function setUp(): void
    {
        $this->warnings = [];

        set_error_handler(function (int $errno, string $message): bool {
            if ($errno === E_USER_WARNING) {
                $this->warnings[] = $message;
            }

            return true;
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function test_discarding_a_map_put_warns(): void
    {
        $map = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());

        $map->put('a', 1);

        self::assertCount(1, $this->warnings);
        self::assertStringContainsString('the receiver is unchanged', $this->warnings[0]);
        self::assertCount(0, $map, 'the discarded put must not have mutated the receiver');
    }

    public function test_using_the_result_does_not_warn(): void
    {
        $map = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());

        $updated = $map->put('a', 1);

        self::assertSame([], $this->warnings);
        self::assertCount(1, $updated);
    }

    public function test_discarding_a_vector_append_warns(): void
    {
        $vector = PersistentVector::empty(new ModuloHasher(), new SimpleEqualizer());

        $vector->append(1);

        self::assertCount(1, $this->warnings);
    }

    public function test_discarding_a_list_prepend_warns(): void
    {
        $list = PersistentList::empty(new ModuloHasher(), new SimpleEqualizer());

        $list->prepend(1);

        self::assertCount(1, $this->warnings);
    }

    public function test_a_copying_with_meta_warns(): void
    {
        $map = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer());

        $map->withMeta(TypeFactory::getInstance()->persistentMapFromArray([]));

        self::assertCount(1, $this->warnings);
    }

    public function test_with_meta_copies_a_symbol_and_leaves_the_receiver_unchanged(): void
    {
        $symbol = Symbol::create('foo');
        $meta = TypeFactory::getInstance()->persistentMapFromArray([]);

        $tagged = $symbol->withMeta($meta);

        self::assertNotSame($symbol, $tagged);
        self::assertNull($symbol->getMeta());
        self::assertSame($meta, $tagged->getMeta());
        self::assertSame([], $this->warnings);
    }

    public function test_discarding_meta_trait_with_meta_warns(): void
    {
        $symbol = Symbol::create('foo');

        $symbol->withMeta(TypeFactory::getInstance()->persistentMapFromArray([]));

        self::assertCount(1, $this->warnings);
        self::assertNull($symbol->getMeta());
    }

    public function test_a_transient_put_is_not_annotated(): void
    {
        // Transients mutate in place and return $this, so discarding is correct.
        // Annotating them would flag working code, including the loop inside
        // TransientMergeStrategyTrait::merge().
        $transient = PersistentArrayMap::empty(new ModuloHasher(), new SimpleEqualizer())
            ->asTransient();

        $transient->put('a', 1);

        self::assertSame([], $this->warnings);
    }
}
