<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\Exceptions\SpliceNotInListException;
use Phel\Compiler\Domain\Reader\QuasiquoteTransformerInterface;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Lang\TypeInterface;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\QuoteNode;

/**
 * @internal
 */
final readonly class QuoasiquoteReader
{
    public function __construct(
        private ReaderInterface $reader,
        private QuasiquoteTransformerInterface $quasiquoteTransformer,
    ) {}

    /**
     * @throws ReaderException
     * @throws SpliceNotInListException
     */
    public function read(QuoteNode $node, NodeInterface $root): float|bool|int|string|TypeInterface|null
    {
        /** @var bool|float|int|string|TypeInterface|null $expression */
        $expression = $this->reader->readExpression($node->getExpression(), $root);
        $result = $this->quasiquoteTransformer->transform($expression);

        if ($result instanceof TypeInterface) {
            return $result
                ->setStartLocation($node->getStartLocation())
                ->setEndLocation($node->getEndLocation());
        }

        return $result;
    }
}
