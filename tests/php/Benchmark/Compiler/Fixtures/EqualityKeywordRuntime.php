<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel\Lang\Equalizer;
use Phel\Lang\Keyword;

/**
 * Baseline emission shape for a `(= x :kw)`-heavy `cond` chain before
 * issue #2561: each keyword comparison dispatches through the runtime
 * equality (`phel.core/=` → `Equalizer::equals`). This models the
 * per-site dispatch cost the native `===` lowering removes.
 */
final readonly class EqualityKeywordRuntime
{
    private Equalizer $equalizer;

    private Keyword $a;

    private Keyword $b;

    private Keyword $c;

    public function __construct()
    {
        $this->equalizer = new Equalizer();
        $this->a = Keyword::create('alpha');
        $this->b = Keyword::create('beta');
        $this->c = Keyword::create('gamma');
    }

    public function __invoke(Keyword $x): int
    {
        if ($this->equalizer->equals($x, $this->a)) {
            return 1;
        }

        if ($this->equalizer->equals($x, $this->b)) {
            return 2;
        }

        if ($this->equalizer->equals($x, $this->c)) {
            return 3;
        }

        return 0;
    }
}
