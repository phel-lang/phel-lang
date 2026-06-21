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
 * Parser throughput micro-benchmark.
 *
 * Parsing allocates one parse-tree node per token (including trivia),
 * so it is the per-node allocation hot path that follows the lexer.
 * Each rev re-lexes the source (the `TokenStream` is a one-shot
 * generator that `parseAll` drains) and then parses it to a single
 * `FileNode`. The lex cost is shared overhead common to both subjects,
 * so deltas still attribute cleanly to the parser.
 *
 * `bench_parse_core` parses the real `phel.core` aggregate; the
 * synthetic `bench_parse_fixture` parses a fixed-shape source with a
 * dense mix of vectors, maps and nested calls to stress collection and
 * list node construction. Both fixtures are deterministic and built
 * once in `setUp`.
 *
 * @BeforeMethods("setUp")
 */
final class ParserBench
{
    private const int FIXTURE_FORMS = 200;

    private CompilerFacade $compilerFacade;

    private string $coreSource = '';

    private string $fixtureSource = '';

    public function setUp(): void
    {
        Phel::bootstrap(__DIR__ . '/../../../../');

        $this->compilerFacade = new CompilerFacade();
        $this->coreSource = $this->readCoreSource();
        $this->fixtureSource = $this->buildFixtureSource();
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function bench_parse_core(): void
    {
        $this->parseToFileNode($this->coreSource);
    }

    /**
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function bench_parse_fixture(): void
    {
        $this->parseToFileNode($this->fixtureSource);
    }

    private function parseToFileNode(string $source): void
    {
        $stream = $this->compilerFacade->lexString($source, LexerInterface::DEFAULT_SOURCE);
        $fileNode = $this->compilerFacade->parseAll($stream);

        // Touch the result so the parse is not optimised away.
        if ($fileNode->getChildren() === []) {
            throw new RuntimeException('parser produced no children');
        }
    }

    private function buildFixtureSource(): string
    {
        $forms = [];
        for ($i = 0; $i < self::FIXTURE_FORMS; ++$i) {
            $forms[] = <<<PHEL
                (defn shape-{$i} [coll]
                  (let [pairs {:a 1 :b 2 :c {$i}}
                        items [1 2 3 [4 5 [6 7]] {$i}]]
                    (map (fn [x] (+ x (get pairs :a))) (concat coll items))))
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
