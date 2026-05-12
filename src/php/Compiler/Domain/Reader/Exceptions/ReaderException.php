<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\Exceptions;

use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Parser\ReadModel\CodeSnippet;

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
        $codeSnippet = new CodeSnippet(
            $root->getStartLocation(),
            $root->getEndLocation(),
            $root->getCode(),
        );

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
