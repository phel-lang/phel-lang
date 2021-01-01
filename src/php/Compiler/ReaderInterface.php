<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ReadModel\ReaderResult;

interface ReaderInterface
{
    public function read(NodeInterface $parseTree): ReaderResult;
}
