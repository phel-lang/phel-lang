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

    /**
     * Reads one parse-tree node into its Phel value.
     *
     * The sub-readers in `ExpressionReader/` recurse through this method, which
     * is why it belongs on the contract: it lets them depend on this Domain
     * interface instead of the concrete `Application\Reader` that owns them.
     *
     * @throws ReaderException
     */
    public function readExpression(NodeInterface $node, NodeInterface $root): mixed;
}
