<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\AtomNode;
use Phel\Lang\AbstractType;

final class AtomReader
{
    /**
     * @return AbstractType|string|float|int|bool|null
     */
    public function read(AtomNode $node)
    {
        $value = $node->getValue();

        if ($value instanceof AbstractType) {
            $value->setStartLocation($node->getStartLocation());
            $value->setEndLocation($node->getEndLocation());
        }

        return $value;
    }
}
