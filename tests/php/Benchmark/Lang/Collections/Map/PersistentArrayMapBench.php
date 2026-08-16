<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Map;

use Phel\Lang\Collections\Map\PersistentArrayMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Equalizer;
use Phel\Lang\Hasher;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\ParamProviders;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Small-map reads, measured through the two construction paths a small map
 * actually comes from.
 *
 * {@see PersistentHashMapBench} measures a forced hash map, and the existing
 * `phel.core` subjects use two-entry maps, so the size range where the
 * representation is chosen — and where the linear scan in
 * `PersistentArrayMap::findIndex()` costs an `Equalizer::equalsKey()` per entry
 * — was unmeasured (#3172).
 *
 * Two things here are deliberate:
 *
 * - **The sizes are literals, not derived from `MAX_SIZE`.** A corpus that
 *   moves with the constant stops being a corpus: the numbers before and after
 *   a threshold change would describe different inputs.
 * - **Both paths are built.** `TypeFactory::persistentMapFromArray()` is what a
 *   `{...}` literal compiles to, and a transient `put` chain is what `assoc`,
 *   `into` and `zipmap` do. Which representation each ends up with is the
 *   thing under measurement, so neither subject forces a class.
 *
 * Keys are keywords, the common case in Phel code and the one whose equality
 * goes through `Keyword::equals` rather than a native `===`.
 */
final class PersistentArrayMapBench
{
    private PersistentMapInterface $literalMap;

    private PersistentMapInterface $grownMap;

    private Keyword $lastKey;

    private Keyword $absentKey;

    /**
     * The last key, so the array-map scan runs to the end: the first key is the
     * best case and would hide the linear walk.
     *
     * @BeforeMethods("setUpMaps")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_find_last_in_literal_map(): void
    {
        $this->literalMap->find($this->lastKey);
    }

    /**
     * The same map built by growing, which is the path `assoc`/`into` take.
     * Paired with the subject above: the two disagreeing is the symptom.
     *
     * @BeforeMethods("setUpMaps")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_find_last_in_grown_map(): void
    {
        $this->grownMap->find($this->lastKey);
    }

    /**
     * A miss is the array map's worst case: the whole array is scanned and no
     * entry matches.
     *
     * @BeforeMethods("setUpMaps")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_find_absent_in_grown_map(): void
    {
        $this->grownMap->find($this->absentKey);
    }

    /**
     * The write side, so a threshold change that speeds reads up is not
     * reported without what it costs to build.
     *
     * @BeforeMethods("setUpMaps")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_put_into_grown_map(): void
    {
        $this->grownMap->put($this->absentKey, 1);
    }

    /**
     * Full traversal, which is the other half of what the representation
     * decides: an array map walks a flat array, a hash map descends the trie.
     *
     * @BeforeMethods("setUpMaps")
     *
     * @ParamProviders("provideSizes")
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_iterate_grown_map(): void
    {
        foreach ($this->grownMap as $value) {
            // Traversal is the measurement; the value is deliberately unused.
        }
    }

    /**
     * Sizes around the threshold, written out rather than computed: 2 and 4 sit
     * below it, 5 is where a `{...}` literal promotes today, and 8 is where a
     * grown map does.
     *
     * @return array<string, array{size: int}>
     */
    public function provideSizes(): array
    {
        return [
            'size 2' => ['size' => 2],
            'size 4' => ['size' => 4],
            'size 5' => ['size' => 5],
            'size 8' => ['size' => 8],
        ];
    }

    /**
     * @param array{size: int} $params
     */
    public function setUpMaps(array $params): void
    {
        $size = $params['size'];
        $typeFactory = new TypeFactory(new Hasher(), new Equalizer());

        $kvs = [];
        for ($i = 0; $i < $size; ++$i) {
            $kvs[] = Keyword::create('k' . $i);
            $kvs[] = $i;
        }

        $this->literalMap = $typeFactory->persistentMapFromArray($kvs);

        $transient = PersistentArrayMap::empty(new Hasher(), new Equalizer())->asTransient();
        for ($i = 0; $i < $size; ++$i) {
            $transient->put(Keyword::create('k' . $i), $i);
        }

        $this->grownMap = $transient->persistent();
        $this->lastKey = Keyword::create('k' . ($size - 1));
        $this->absentKey = Keyword::create('absent');
    }
}
