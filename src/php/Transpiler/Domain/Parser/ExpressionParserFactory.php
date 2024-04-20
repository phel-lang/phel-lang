<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Parser;

use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Transpiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Transpiler\Domain\Parser\ExpressionParser\ListParser;
use Phel\Transpiler\Domain\Parser\ExpressionParser\MetaParser;
use Phel\Transpiler\Domain\Parser\ExpressionParser\QuoteParser;
use Phel\Transpiler\Domain\Parser\ExpressionParser\StringParser;

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
