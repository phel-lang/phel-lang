<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;

interface ReaderInterface
{
    /**
     * @throws ReaderException
     */
    public function read(NodeInterface $node): ReaderResult;
}
