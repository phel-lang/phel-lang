<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules;

use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;

interface RuleInterface
{
    /**
     * @throws ZipperException
     */
    public function transform(NodeInterface $node): NodeInterface;
}
