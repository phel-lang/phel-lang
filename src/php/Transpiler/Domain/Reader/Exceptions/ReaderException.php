<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Reader\Exceptions;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ReadModel\CodeSnippet;

final class ReaderException extends AbstractLocatedException
{
    private function __construct(
        string $message,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        private readonly CodeSnippet $codeSnippet,
    ) {
        parent::__construct($message, $startLocation, $endLocation);
    }

    public static function forNode(NodeInterface $node, NodeInterface $root, string $message): self
    {
        $codeSnippet = CodeSnippet::fromNode($root);

        return new self(
            $message,
            $node->getStartLocation(),
            $node->getEndLocation(),
            $codeSnippet,
        );
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
