<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\AbstractAtomNode;
use Phel\Lang\TypeInterface;

final class AtomReader
{
    /**
     * @return TypeInterface|string|float|int|bool|null
     */
    public function read(AbstractAtomNode $node)
    {
        $value = $node->getValue();

        if ($value instanceof TypeInterface) {
            $value->setStartLocation($node->getStartLocation());
            $value->setEndLocation($node->getEndLocation());
        }

        return $value;
    }
}
