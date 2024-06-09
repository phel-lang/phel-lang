<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser;

use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\StringParser;

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
