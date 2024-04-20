<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain;

use Phel\Formatter\Domain\Rules\RuleInterface;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Transpiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Transpiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\TranspilerFacadeInterface;

final readonly class Formatter implements FormatterInterface
{
    /**
     * @param list<RuleInterface> $rules
     */
    public function __construct(
        private TranspilerFacadeInterface $transpilerFacade,
        private array $rules,
    ) {
    }

    /**
     * @throws AbstractParserException
     * @throws LexerValueException
     * @throws ZipperException
     */
    public function format(string $string, string $source = self::DEFAULT_SOURCE): string
    {
        $tokenStream = $this->transpilerFacade->lexString($string, $source);
        $fileNode = $this->transpilerFacade->parseAll($tokenStream);

        return $this->formatNode($fileNode)->getCode();
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
