<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Generator;
use Phel\Compiler\Parser;
use Phel\Compiler\Parser\ParserNode\ListNode;

final class ListParser
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function parse(Generator $tokenStream, int $endTokenType, int $tokenType): ListNode
    {
        $acc = [];
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        while ($tokenStream->valid()) {
            $token = $tokenStream->current();

            if ($token->getType() === $endTokenType) {
                $endLocation = $token->getEndLocation();
                $tokenStream->next();

                return new ListNode($tokenType, $startLocation, $endLocation, $acc);
            }

            $acc[] = $this->parser->readExpression($tokenStream);
        }

        throw $this->parser->buildParserException('Unterminated list');
    }
}
