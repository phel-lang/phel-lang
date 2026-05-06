<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel\Compiler\Domain\Parser\ParserNode\AbstractAtomNode;
use Phel\Lang\TypeInterface;

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
