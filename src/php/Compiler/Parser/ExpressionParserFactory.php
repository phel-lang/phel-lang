<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Parser\ExpressionParser\StringParser;

final class ExpressionParserFactory implements ExpressionParserFactoryInterface
{
    public function createAtomParser(GlobalEnvironmentInterface $globalEnvironment): AtomParser
    {
        return new AtomParser($globalEnvironment);
    }

    public function createStringParser(): StringParser
    {
        return new StringParser();
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
}
