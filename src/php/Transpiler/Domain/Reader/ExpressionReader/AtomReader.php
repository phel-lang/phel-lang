<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Reader\ExpressionReader;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\AbstractAtomNode;

final class AtomReader
{
    public function read(AbstractAtomNode $node): float|bool|int|string|TypeInterface|null
    {
        $value = $node->getValue();

        if ($value instanceof TypeInterface) {
            $value->setStartLocation($node->getStartLocation());
            $value->setEndLocation($node->getEndLocation());
        }

        return $value;
    }
}
