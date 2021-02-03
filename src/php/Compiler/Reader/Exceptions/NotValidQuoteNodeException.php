<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\Exceptions;

use Phel\Compiler\Parser\ParserNode\QuoteNode;
use RuntimeException;

final class NotValidQuoteNodeException extends RuntimeException
{
    public static function forNode(QuoteNode $node): self
    {
        return new self('Not a valid QuoteNode: ' . get_class($node));
    }
}
