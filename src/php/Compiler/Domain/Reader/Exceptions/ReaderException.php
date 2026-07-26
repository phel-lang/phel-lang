<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\Exceptions;

use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

/**
 * @internal
 */
final class ReaderException extends AbstractLocatedException
{
    private function __construct(
        string $message,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        private readonly CodeSnippet $codeSnippet,
        ?Throwable $nestedException = null,
    ) {
        parent::__construct($message, $startLocation, $endLocation, $nestedException);
    }

    /**
     * `$nestedException` keeps the original throw site (a failing tag handler,
     * for example) reachable via `getPrevious()`; the located message shown to
     * the user is unaffected.
     */
    public static function forNode(
        NodeInterface $node,
        NodeInterface $root,
        string $message,
        ?Throwable $nestedException = null,
    ): self {
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
            $nestedException,
        );
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
