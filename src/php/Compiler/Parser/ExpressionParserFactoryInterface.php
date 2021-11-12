<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Parser\ExpressionParser\StringParser;

interface ExpressionParserFactoryInterface
{
    public function createAtomParser(GlobalEnvironmentInterface $globalEnvironment): AtomParser;

    public function createStringParser(): StringParser;

    public function createListParser(Parser $parser): ListParser;

    public function createQuoteParser(Parser $parser): QuoteParser;

    public function createMetaParser(Parser $parser): MetaParser;
}
