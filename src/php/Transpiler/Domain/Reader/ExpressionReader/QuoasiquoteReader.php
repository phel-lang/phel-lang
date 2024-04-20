<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Reader\ExpressionReader;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\QuoteNode;
use Phel\Transpiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Transpiler\Domain\Reader\Exceptions\SpliceNotInListException;
use Phel\Transpiler\Domain\Reader\QuasiquoteTransformerInterface;
use Phel\Transpiler\Domain\Reader\Reader;

final readonly class QuoasiquoteReader
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
            return $result
                ->setStartLocation($node->getStartLocation())
                ->setEndLocation($node->getEndLocation());
        }

        return $result;
    }
}
