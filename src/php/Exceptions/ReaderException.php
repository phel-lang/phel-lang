<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Exception;
use Phel\Compiler\Exceptions\PhelCodeException;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Lang\SourceLocation;

final class ReaderException extends PhelCodeException
{
    private CodeSnippet $codeSnippet;

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

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
