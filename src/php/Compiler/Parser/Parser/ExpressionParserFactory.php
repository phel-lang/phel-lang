<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Parser;

use Phel\Compiler\Parser\Parser;
use Phel\Compiler\Parser\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Parser\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Parser\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Parser\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Parser\Parser\ExpressionParser\StringParser;

final class ExpressionParserFactory implements ExpressionParserFactoryInterface
{
    public function createAtomParser(): AtomParser
    {
        return new AtomParser();
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
