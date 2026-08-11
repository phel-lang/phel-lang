<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Override;
use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

use function htmlspecialchars;

/**
 * `phel.html/escape-html` (#3021 B4). The two flags it passes to
 * `htmlspecialchars` are compile-time constants that were combined on every
 * call, and `bit-or` carries an `:inline` that expands to a closure allocated
 * per call in expression position. Hoisting them into a `def-` took the
 * function from 2.31μs to 0.63μs against a ~0.13μs empty-closure floor.
 *
 * The pair is what to review. `bench_escape_html_raw` is the `htmlspecialchars`
 * call the body reduces to, so the gap is the Phel call and nothing else; it
 * widens the moment an expression creeps back into the argument list.
 *
 * The subject matters more than its ratio suggests: `escape-html` runs once per
 * attribute name, once per attribute value and once per text node, so it is on
 * the hot path of rendering anything at all.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class StdlibHtmlBench extends CoreBenchCase
{
    /** @var callable */
    private $escapeHtml;

    private string $markup = '';

    private int $flags = 0;

    /** @var callable */
    private $html;

    private mixed $document = null;

    /**
     * @Revs(1000)
     */
    public function bench_escape_html(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->escapeHtml)($this->markup);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_escape_html_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            htmlspecialchars($this->markup, $this->flags);
        }
    }

    /**
     * One `Revs(100)` render rather than the `INNER` loop the O(1) subjects
     * use: this subject is already hundreds of operations, so the timer is
     * negligible against it and looping would only lengthen the run.
     *
     * Unpaired on purpose. There is no raw-PHP twin for a whole render; what
     * this guards is the absolute move, 110.5μs to 92.2μs.
     *
     * @Revs(100)
     */
    public function bench_render_document(): void
    {
        ($this->html)($this->document);
    }

    #[Override]
    protected function extraNamespaces(): array
    {
        return ['phel.html'];
    }

    protected function setUpFixtures(): void
    {
        $this->escapeHtml = $this->phelFn('phel.html', 'escape-html');
        // Every character the flags act on, so neither side measures a no-op.
        $this->markup = '<div class="x">Hello & \'world\'</div>';
        $this->flags = ENT_QUOTES | ENT_SUBSTITUTE;

        // `render-html`, not the `html` macro: the macro compiles a static
        // vector at expansion time and emits a `render-html` call for anything
        // dynamic, so this is the path a runtime render actually takes.
        $this->html = $this->phelFn('phel.html', 'render-html');
        $this->document = Phel::vector([
            Phel::keyword('div'),
            Phel::map(
                Phel::keyword('class'),
                Phel::vector(['a', 'b']),
                Phel::keyword('id'),
                'main',
                Phel::keyword('data-x'),
                'q"uote',
            ),
            Phel::vector([Phel::keyword('p'), 'text & <tag>']),
            Phel::vector([
                Phel::keyword('span'),
                Phel::map(Phel::keyword('title'), "t'x"),
                'more & text',
            ]),
        ]);
    }
}
