<?php

declare(strict_types=1);

namespace Phel\Formatter\Rules;

use Phel\Compiler\Parser\ParserNode\NodeInterface;

interface RuleInterface
{
    public function transform(NodeInterface $node): NodeInterface;
}
