<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Override;
use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;

use PhpBench\Benchmark\Metadata\Annotations\Revs;

use function implode;
use function mb_strpos;
use function preg_split;
use function rtrim;
use function str_pad;
use function strlen;
use function strspn;

/**
 * The `phel.string` functions rewritten for #3050, #3051 and #3052, one subject
 * per distinct cause so a regression names itself:
 *
 * - `trim_newline` walked backwards with `mb_substr`, two calls per trailing
 *   character, where `rtrim` does it in one.
 * - `index_of` computed a `max`/`min` clamp over `mb_strlen` to arrive back at
 *   offset 0, on every two-argument call.
 * - `blank` ran a unicode regex on every input; the `strspn` prefix now settles
 *   any string whose first non-blank byte is ASCII, which is most of them.
 * - `pad_left` reached its default pad string through a `defn-` and a rest
 *   destructure.
 *
 * Each is paired with the PHP its body now reduces to. Review the ratio, not
 * the duration: it is what stays flat across machines, and what moves if a
 * guard or a conversion creeps back in.
 *
 * `assert-string` is the one thing every subject shares. It used to be a
 * `defn-`, so each guard cost a registry lookup before it could look at the
 * value; it is `:inline` now and compiles to `is_string`. That is why the Phel
 * side of each pair sits close to its raw twin rather than a call above it.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class StdlibStringBench extends CoreBenchCase
{
    /** ASCII whitespace, matching `phel.string`'s own charlist. */
    private const string BLANK_CHARS = " \t\n\x0B\f\r\x1C\x1D\x1E\x1F";

    /** @var callable */
    private $trimNewline;

    /** @var callable */
    private $indexOf;

    /** @var callable */
    private $blank;

    /** @var callable */
    private $padLeft;

    /** @var callable */
    private $join;

    /** @var callable */
    private $reverse;

    /** @var callable */
    private $escape;

    private mixed $escapeMap = null;

    /** @var callable */
    private $split;

    private string $trailing = '';

    private string $haystack = '';

    private string $sentence = '';

    private string $notBlank = '';

    /** Built once: the pair measures the join, not the vector construction. */
    private mixed $parts = null;

    /**
     * @Revs(1000)
     */
    public function bench_trim_newline(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->trimNewline)($this->trailing);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_trim_newline_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            rtrim($this->trailing, "\n\r");
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_index_of(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->indexOf)($this->haystack, 'world');
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_index_of_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            mb_strpos($this->haystack, 'world', 0);
        }
    }

    /**
     * The non-blank input is the one that matters: a blank string stops on the
     * `strspn` run length, while this one has to reject on the first byte.
     *
     * @Revs(1000)
     */
    public function bench_blank(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->blank)($this->notBlank);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_blank_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            $unused = strspn($this->notBlank, self::BLANK_CHARS) === strlen($this->notBlank);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_pad_left(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->padLeft)($this->haystack, 40);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_pad_left_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            str_pad($this->haystack, 40, ' ', STR_PAD_LEFT);
        }
    }

    /**
     * `reverse` converted three times: `mb_str_split` to a PHP array,
     * `core/reverse` to a Phel sequence, `join` back to a PHP array. Now
     * `array_reverse` and `implode` on the split array. 7.23μs to 0.83μs.
     *
     * @Revs(1000)
     */
    public function bench_reverse(): void
    {
        ($this->reverse)($this->sentence);
    }

    /**
     * `escape` called a closure per character, and that closure invoked the
     * Phel map itself. The map is now read once into a PHP array and indexed.
     * 20.05μs to 3.48μs.
     *
     * @Revs(1000)
     */
    public function bench_escape(): void
    {
        ($this->escape)($this->sentence, $this->escapeMap);
    }

    /**
     * `split` gained a pattern guard, so it is worth a subject: the guard runs
     * on every call and the function is used per line or per field in most
     * text handling.
     *
     * @Revs(1000)
     */
    public function bench_split(): void
    {
        ($this->split)($this->sentence, '/\s+/');
    }

    /**
     * @Revs(1000)
     */
    public function bench_split_raw(): void
    {
        Phel::vector(preg_split('/\s+/', $this->sentence, -1));
    }

    /**
     * Unlike the pairs above this one does not converge: the residual is
     * `to-array`, which is `(apply php/array coll)` (item A6 of #3021) and
     * lives in `phel.core`. The gap is the measurement of that item.
     *
     * @Revs(1000)
     */
    public function bench_join(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->join)(', ', $this->parts);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_join_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            implode(', ', ['apple', 'banana', 'cherry']);
        }
    }

    #[Override]
    protected function extraNamespaces(): array
    {
        return ['phel.string'];
    }

    protected function setUpFixtures(): void
    {
        $this->trimNewline = $this->phelFn('phel.string', 'trim-newline');
        $this->indexOf = $this->phelFn('phel.string', 'index-of');
        $this->blank = $this->phelFn('phel.string', 'blank?');
        $this->padLeft = $this->phelFn('phel.string', 'pad-left');
        $this->join = $this->phelFn('phel.string', 'join');
        $this->split = $this->phelFn('phel.string', 'split');
        $this->reverse = $this->phelFn('phel.string', 'reverse');
        $this->escape = $this->phelFn('phel.string', 'escape');
        $this->escapeMap = Phel::map('o', '0', 'e', '3');

        $this->trailing = "hello world\n\r\n";
        $this->haystack = 'hello world world';
        $this->notBlank = '  hello  ';
        $this->parts = Phel::vector(['apple', 'banana', 'cherry']);
        $this->sentence = 'the quick brown fox jumps over the lazy dog again and again';
    }
}
