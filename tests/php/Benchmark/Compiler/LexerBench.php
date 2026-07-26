<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function sprintf;

/**
 * Lexer throughput micro-benchmark.
 *
 * The lexer walks the source string character by character, so it is a
 * hot path for every compile. Each subject lexes a fixed source to
 * exhaustion (the `TokenStream` wraps a one-shot generator, so the
 * `foreach` is what drives token production) and the per-rev cost is
 * dominated by tokenisation rather than allocation.
 *
 * Two synthetic fixtures isolate the cost of the UTF-8-aware character
 * scan: `bench_lex_ascii` and `bench_lex_utf8` share the same token
 * shape and length but differ only in whether identifiers, strings and
 * comments carry multibyte code points, so a regression in the
 * multibyte path shows up as a divergence between the two.
 *
 * A third subject lexes the real `phel.core` aggregate source so the
 * realistic token mix (symbols, keywords, parens, strings, comments)
 * is covered too. All fixtures are built once in `setUp` and are
 * deterministic — no wall-clock or cache dependency.
 *
 * @BeforeMethods("setUp")
 */
final class LexerBench
{
    private const int FIXTURE_FORMS = 200;

    private CompilerFacade $compilerFacade;

    private string $asciiSource = '';

    private string $utf8Source = '';

    private string $coreSource = '';

    public function setUp(): void
    {
        Phel::bootstrap(__DIR__ . '/../../../../');

        $this->compilerFacade = new CompilerFacade();
        $this->asciiSource = $this->buildSource(asciiOnly: true);
        $this->utf8Source = $this->buildSource(asciiOnly: false);
        $this->coreSource = $this->readCoreSource();
    }

    /**
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function bench_lex_ascii(): void
    {
        $this->lexToExhaustion($this->asciiSource);
    }

    /**
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function bench_lex_utf8(): void
    {
        $this->lexToExhaustion($this->utf8Source);
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function bench_lex_core(): void
    {
        $this->lexToExhaustion($this->coreSource);
    }

    private function lexToExhaustion(string $source): void
    {
        $stream = $this->compilerFacade->lexString($source, LexerInterface::DEFAULT_SOURCE);

        $count = 0;
        foreach ($stream as $_token) {
            ++$count;
        }

        // Touch the count so the loop is not optimised away.
        if ($count < 0) {
            throw new RuntimeException('unreachable');
        }
    }

    /**
     * Builds a deterministic Phel source string of fixed shape. When
     * `$asciiOnly` is false the identifiers, doc strings and comments
     * carry multibyte code points so the UTF-8 scan path is exercised
     * while keeping the token count identical to the ASCII variant.
     */
    private function buildSource(bool $asciiOnly): string
    {
        $label = $asciiOnly ? 'alpha' : 'alphá';
        $doc = $asciiOnly ? 'doubles the input value' : 'doüblés thé ínpüt válüé';
        $comment = $asciiOnly ? '; a plain comment line' : '; ä cómmént wíth áccénts';

        $forms = [];
        for ($i = 0; $i < self::FIXTURE_FORMS; ++$i) {
            $forms[] = <<<PHEL
                {$comment}
                (defn fixture-{$label}-{$i} [x y]
                  "{$doc}"
                  (let [sum (+ x y)
                        items [1 2 3 :{$label} "{$doc}"]]
                    {:result (* sum {$i}) :items items}))
                PHEL;
        }

        return implode("\n\n", $forms) . "\n";
    }

    private function readCoreSource(): string
    {
        $path = __DIR__ . '/../../../../src/phel/core.phel';
        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException(sprintf('Unable to read core source at %s', $path));
        }

        return $source;
    }
}
