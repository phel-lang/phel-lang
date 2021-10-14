<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;

interface RuleInterface
{
    /**
     * @throws ZipperException
     */
    public function transform(NodeInterface $node): NodeInterface;
}
