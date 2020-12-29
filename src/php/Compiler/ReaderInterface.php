<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\ParserNode\NodeInterface;
use Phel\Compiler\ReadModel\ReaderResult;

interface ReaderInterface
{
    public function read(NodeInterface $parseTree): ReaderResult;
}
