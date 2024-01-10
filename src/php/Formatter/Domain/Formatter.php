<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain;

use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Domain\Rules\RuleInterface;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;

final readonly class Formatter implements FormatterInterface
{
    /**
     * @param list<RuleInterface> $rules
     */
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
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
        $tokenStream = $this->compilerFacade->lexString($string, $source);
        $fileNode = $this->compilerFacade->parseAll($tokenStream);

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
