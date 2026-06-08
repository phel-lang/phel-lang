<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\Token;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `AtomParser::parse()` micro-benchmark over symbol tokens — the common
 * atom in real source. Symbols take the first-char guard fast-path
 * instead of running the full anchored number-regex gauntlet.
 */
final class AtomParserBench
{
    private AtomParser $parser;

    /** @var list<Token> */
    private array $tokens = [];

    public function setUp(): void
    {
        $this->parser = new AtomParser(new GlobalEnvironment());

        $words = ['map', 'filter', 'defn', 'reduce', 'partition', 'keyword?', '->>', 'some-fn', 'first', 'assoc'];
        $start = new SourceLocation('bench', 0, 0);
        $end = new SourceLocation('bench', 0, 1);
        $tokens = [];
        foreach ($words as $word) {
            $tokens[] = new Token(Token::T_ATOM, $word, $start, $end);
        }

        $this->tokens = $tokens;
    }

    /**
     * @Revs(20000)
     *
     * @Iterations(5)
     *
     * @BeforeMethods("setUp")
     */
    public function bench_parse_symbols(): void
    {
        foreach ($this->tokens as $token) {
            $this->parser->parse($token);
        }
    }
}
