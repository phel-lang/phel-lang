<?php

declare(strict_types=1);

namespace Phel\Formatter\Formatter;

use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Exceptions\ZipperException;
use Phel\Formatter\Rules\RuleInterface;

final class Formatter implements FormatterInterface
{
    private CompilerFacadeInterface $compilerFacade;
    /** @var RuleInterface[] */
    private array $rules;

    /**
     * @param RuleInterface[] $rules
     */
    public function __construct(
        CompilerFacadeInterface $compilerFacade,
        array $rules
    ) {
        $this->compilerFacade = $compilerFacade;
        $this->rules = $rules;
    }

    /**
     * @throws AbstractParserException
     * @throws LexerValueException
     * @throws ZipperException
     */
    public function format(string $string, string $source = self::DEFAULT_SOURCE): string
    {
        $tokenStream = $this->compilerFacade->lexString($string, $withLocation = true, $source);
        $fileNode = $this->compilerFacade->parseAll($tokenStream);
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
