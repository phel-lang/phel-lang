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

interface ExpressionParserFactoryInterface
{
    public function createAtomParser(GlobalEnvironmentInterface $globalEnvironment): AtomParser;

    public function createStringParser(): StringParser;

    public function createListParser(Parser $parser): ListParser;

    public function createQuoteParser(Parser $parser): QuoteParser;

    public function createMetaParser(Parser $parser): MetaParser;
}
