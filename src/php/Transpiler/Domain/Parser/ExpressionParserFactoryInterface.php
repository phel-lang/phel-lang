<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Parser;

use Phel\Transpiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Transpiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Transpiler\Domain\Parser\ExpressionParser\ListParser;
use Phel\Transpiler\Domain\Parser\ExpressionParser\MetaParser;
use Phel\Transpiler\Domain\Parser\ExpressionParser\QuoteParser;
use Phel\Transpiler\Domain\Parser\ExpressionParser\StringParser;

interface ExpressionParserFactoryInterface
{
    public function createAtomParser(GlobalEnvironmentInterface $globalEnvironment): AtomParser;

    public function createStringParser(): StringParser;

    public function createListParser(Parser $parser): ListParser;

    public function createQuoteParser(Parser $parser): QuoteParser;

    public function createMetaParser(Parser $parser): MetaParser;
}
