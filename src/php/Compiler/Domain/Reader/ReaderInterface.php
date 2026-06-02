<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Shared\Parser\Node\NodeInterface;

interface ReaderInterface
{
    /**
     * @throws ReaderException
     */
    public function read(NodeInterface $node): ReaderResult;
}
