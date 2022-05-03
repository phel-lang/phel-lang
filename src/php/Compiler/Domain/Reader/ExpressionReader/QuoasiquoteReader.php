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
    private Reader $reader;
    private QuasiquoteTransformerInterface $quasiquoteTransformer;

    public function __construct(Reader $reader, QuasiquoteTransformerInterface $quasiquoteTransformer)
    {
        $this->reader = $reader;
        $this->quasiquoteTransformer = $quasiquoteTransformer;
    }

    /**
     * @throws ReaderException
     * @throws SpliceNotInListException
     *
     * @return TypeInterface|string|float|int|bool|null
     */
    public function read(QuoteNode $node, NodeInterface $root)
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
