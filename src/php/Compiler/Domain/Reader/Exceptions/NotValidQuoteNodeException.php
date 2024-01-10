<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\Exceptions;

use Phel\Compiler\Domain\Parser\ParserNode\QuoteNode;
use RuntimeException;

final class NotValidQuoteNodeException extends RuntimeException
{
    public static function forNode(QuoteNode $node): self
    {
        return new self('Not a valid QuoteNode: ' . $node::class);
    }
}
