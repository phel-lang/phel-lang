<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Reader;

use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Transpiler\Domain\Reader\Exceptions\ReaderException;

interface ReaderInterface
{
    /**
     * @throws ReaderException
     */
    public function read(NodeInterface $node): ReaderResult;
}
