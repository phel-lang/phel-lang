<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\AbstractAtomNode;
use Phel\Lang\AbstractType;

final class AtomReader
{
    /**
     * @return AbstractType|string|float|int|bool|null
     */
    public function read(AbstractAtomNode $node)
    {
        $value = $node->getValue();

        if ($value instanceof AbstractType) {
            $value->setStartLocation($node->getStartLocation());
            $value->setEndLocation($node->getEndLocation());
        }

        return $value;
    }
}
