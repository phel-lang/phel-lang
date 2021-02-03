<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Reader\Exceptions\ReaderException;

interface ReaderInterface
{
    /**
     * @throws ReaderException
     */
    public function read(NodeInterface $parseTree): ReaderResult;
}
