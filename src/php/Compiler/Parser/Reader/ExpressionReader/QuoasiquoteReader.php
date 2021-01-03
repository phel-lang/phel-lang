<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Reader\ExpressionReader;

use Phel\Compiler\Parser\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Parser\Reader;
use Phel\Compiler\Parser\Reader\QuasiquoteTransformerInterface;
use Phel\Lang\AbstractType;

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
     * @return AbstractType|string|float|int|bool|null
     */
    public function read(QuoteNode $node)
    {
        $expression = $this->reader->readExpression($node->getExpression());
        $result = $this->quasiquoteTransformer->transform($expression);

        if ($result instanceof AbstractType) {
            $result->setStartLocation($node->getStartLocation());
            $result->setEndLocation($node->getEndLocation());
        }

        return $result;
    }
}
