<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser;

use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\CharParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\ReaderConditionalParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\RegexParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\StringParser;

final class ExpressionParserFactory implements ExpressionParserFactoryInterface
{
    /**
     * The dependency-free sub-parsers are stateless — every input reaches
     * them as a `parse()` argument, nothing is stored per call — so one
     * instance is reused for the lifetime of the factory instead of
     * allocating a fresh object per parsed node. The `Parser`-dependent
     * sub-parsers are not memoised here because they close over a
     * specific `Parser`; the `Parser` builds those once in its own
     * constructor.
     */
    private ?StringParser $stringParser = null;

    private ?CharParser $charParser = null;

    private ?RegexParser $regexParser = null;

    public function createAtomParser(GlobalEnvironmentInterface $globalEnvironment): AtomParser
    {
        return new AtomParser($globalEnvironment);
    }

    public function createStringParser(): StringParser
    {
        return $this->stringParser ??= new StringParser();
    }

    public function createCharParser(): CharParser
    {
        return $this->charParser ??= new CharParser();
    }

    public function createRegexParser(): RegexParser
    {
        return $this->regexParser ??= new RegexParser();
    }

    public function createListParser(Parser $parser): ListParser
    {
        return new ListParser($parser);
    }

    public function createQuoteParser(Parser $parser): QuoteParser
    {
        return new QuoteParser($parser);
    }

    public function createMetaParser(Parser $parser): MetaParser
    {
        return new MetaParser($parser);
    }

    public function createReaderConditionalParser(Parser $parser): ReaderConditionalParser
    {
        return new ReaderConditionalParser($parser);
    }
}
