<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\Exceptions\SpliceNotInListException;
use Phel\Compiler\Domain\Reader\QuasiquoteTransformerInterface;
use Phel\Compiler\Domain\Reader\Reader;
use Phel\Lang\TypeInterface;

final class QuoasiquoteReader
{
    public function __construct(
        private Reader $reader,
        private QuasiquoteTransformerInterface $quasiquoteTransformer,
    ) {
    }

    /**
     * @throws ReaderException
     * @throws SpliceNotInListException
     */
    public function read(QuoteNode $node, NodeInterface $root): float|bool|int|string|TypeInterface|null
    {
        $expression = $this->reader->readExpression($node->getExpression(), $root);
        $result = $this->quasiquoteTransformer->transform($expression);

        if ($result instanceof TypeInterface) {
            $result = $result
                ->setStartLocation($node->getStartLocation())
                ->setEndLocation($node->getEndLocation());
        }

        return $result;
    }
}
