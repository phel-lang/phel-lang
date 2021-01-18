<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Rules\IndentRule;
use Phel\Formatter\Rules\RemoveSurroundingWhitespaceRule;
use Phel\Formatter\Rules\RemoveTrailingWhitespaceRule;
use Phel\Formatter\Rules\RuleInterface;
use Phel\Formatter\Rules\UnindentRule;

class Formatter
{
    private LexerInterface $lexer;
    private ParserInterface $parser;
    /** @var RuleInterface[] */
    private array $rules = [];

    public const DEFAULT_SOURCE = 'string';

    public function __construct(
        LexerInterface $lexer,
        ParserInterface $parser
    ) {
        $this->lexer = $lexer;
        $this->parser = $parser;
        $this->rules = [
            new RemoveSurroundingWhitespaceRule(),
            new UnindentRule(),
            new IndentRule(),
            new RemoveTrailingWhitespaceRule(),
        ];
    }

    public function formatFile(string $filename): void
    {
        $code = file_get_contents($filename);
        $formattedCode = $this->formatString($code, $filename);
        file_put_contents($filename, $formattedCode);
    }

    public function formatString(string $string, string $source = self::DEFAULT_SOURCE): string
    {
        $tokenStream = $this->lexer->lexString($string, $source);
        $fileNode = $this->parser->parseAll($tokenStream);
        $formattedNode = $this->formatNode($fileNode);

        return $formattedNode->getCode();
    }

    public function formatNode(NodeInterface $node): NodeInterface
    {
        $formattedNode = $node;
        foreach ($this->rules as $rule) {
            $formattedNode = $rule->transform($formattedNode);
        }

        return $formattedNode;
    }
}
