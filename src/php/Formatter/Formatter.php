<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Exceptions\ZipperException;
use Phel\Formatter\Rules\RuleInterface;

final class Formatter implements FormatterInterface
{
    private LexerInterface $lexer;
    private ParserInterface $parser;
    /** @var RuleInterface[] */
    private array $rules;

    /**
     * @param RuleInterface[] $rules
     */
    public function __construct(
        LexerInterface $lexer,
        ParserInterface $parser,
        array $rules
    ) {
        $this->lexer = $lexer;
        $this->parser = $parser;
        $this->rules = $rules;
    }

    /**
     * @throws AbstractParserException
     * @throws LexerValueException
     * @throws ZipperException
     */
    public function format(string $string, string $source = self::DEFAULT_SOURCE): string
    {
        $tokenStream = $this->lexer->lexString($string, $source);
        $fileNode = $this->parser->parseAll($tokenStream);
        $formattedNode = $this->formatNode($fileNode);

        return $formattedNode->getCode();
    }

    /**
     * @throws ZipperException
     */
    private function formatNode(NodeInterface $node): NodeInterface
    {
        $formattedNode = $node;
        foreach ($this->rules as $rule) {
            $formattedNode = $rule->transform($formattedNode);
        }

        return $formattedNode;
    }
}
