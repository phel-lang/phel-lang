<?php

declare(strict_types=1);

namespace Phel\Formatter\Rules;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Exceptions\ZipperException;

interface RuleInterface
{
    /**
     * @throws ZipperException
     */
    public function transform(NodeInterface $node): NodeInterface;
}
