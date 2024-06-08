<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use Exception;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeInterface;

use function count;

final class AnalyzerException extends AbstractLocatedException
{
    public static function withLocation(string $message, TypeInterface $type, ?Exception $nested = null): self
    {
        return new self(
            $message,
            $type->getStartLocation(),
            $type->getEndLocation(),
            $nested,
        );
    }

    public static function notEnoughArgsProvided(GlobalVarNode $f, PersistentListInterface $list, int $minArity): self
    {
        return self::withLocation(
            sprintf(
                'Not enough arguments provided to function "%s\\%s". Got: %d Expected: %d',
                $f->getNamespace(),
                $f->getName()->getName(),
                count($list->rest()),
                $minArity,
            ),
            $list,
        );
    }
}
