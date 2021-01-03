<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Parser;

use Phel\Compiler\Parser\Parser;
use Phel\Compiler\Parser\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Parser\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Parser\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Parser\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Parser\Parser\ExpressionParser\StringParser;

interface ExpressionParserFactoryInterface
{
    public function createAtomParser(): AtomParser;

    public function createStringParser(): StringParser;

    public function createListParser(Parser $parser): ListParser;

    public function createQuoteParser(Parser $parser): QuoteParser;

    public function createMetaParser(Parser $parser): MetaParser;
}
