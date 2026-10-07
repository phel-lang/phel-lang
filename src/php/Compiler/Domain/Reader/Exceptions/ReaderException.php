<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\Exceptions;

use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

/**
 * Starts as `READER_ERROR`, the code `phel analyze` reports for a reader
 * error with no more specific one, so `phel run` prints it too (#3537).
 *
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
        $this->setErrorCode(ErrorCode::READER_ERROR);
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
        ?ErrorCode $errorCode = null,
    ): self {
        $codeSnippet = new CodeSnippet(
            $root->getStartLocation(),
            $root->getEndLocation(),
            $root->getCode(),
        );

        $e = new self(
            $message,
            $node->getStartLocation(),
            $node->getEndLocation(),
            $codeSnippet,
            $nestedException,
        );

        if ($errorCode instanceof ErrorCode) {
            $e->setErrorCode($errorCode);
        }

        return $e;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
