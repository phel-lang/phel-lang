<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Parser\ExpressionParser\AtomParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\CharParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\ListParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\MetaParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\QuoteParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\ReaderConditionalParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\RegexParser;
use Phel\Compiler\Domain\Parser\ExpressionParser\StringParser;

interface ExpressionParserFactoryInterface
{
    public function createAtomParser(GlobalEnvironmentInterface $globalEnvironment): AtomParser;

    public function createStringParser(): StringParser;

    public function createCharParser(): CharParser;

    public function createRegexParser(): RegexParser;

    public function createListParser(ParserInterface $parser): ListParser;

    public function createQuoteParser(ParserInterface $parser): QuoteParser;

    public function createMetaParser(ParserInterface $parser): MetaParser;

    public function createReaderConditionalParser(ParserInterface $parser): ReaderConditionalParser;
}
