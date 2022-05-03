<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\Exceptions;

use Phel\Compiler\Domain\Parser\ParserNode\QuoteNode;
use RuntimeException;

use function get_class;

final class NotValidQuoteNodeException extends RuntimeException
{
    public static function forNode(QuoteNode $node): self
    {
        return new self('Not a valid QuoteNode: ' . get_class($node));
    }
}
