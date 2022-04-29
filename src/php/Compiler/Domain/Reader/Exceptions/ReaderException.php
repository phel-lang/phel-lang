<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\Exceptions;

use Exception;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Lang\SourceLocation;

final class ReaderException extends AbstractLocatedException
{
    private CodeSnippet $codeSnippet;

    private function __construct(
        string $message,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        CodeSnippet $codeSnippet,
        ?Exception $nestedException = null
    ) {
        parent::__construct($message, $startLocation, $endLocation, $nestedException);
        $this->codeSnippet = $codeSnippet;
    }

    public static function forNode(NodeInterface $node, NodeInterface $root, string $message): self
    {
        $codeSnippet = CodeSnippet::fromNode($root);

        return new self(
            $message,
            $node->getStartLocation(),
            $node->getEndLocation(),
            $codeSnippet
        );
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
