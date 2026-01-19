<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use Exception;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeInterface;

use function count;
use function implode;
use function sprintf;

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

    /**
     * @param array<string> $suggestions Similar symbol names for "did you mean?" hint
     */
    public static function cannotResolveSymbol(string $symbolName, TypeInterface $type, array $suggestions = []): self
    {
        $message = sprintf("Cannot resolve symbol '%s'", $symbolName);

        if ($suggestions !== []) {
            $message .= sprintf('. Did you mean %s?', self::formatSuggestions($suggestions));
        }

        return self::withLocation($message, $type);
    }

    public static function notEnoughArgsProvided(
        GlobalVarNode $f,
        PersistentListInterface $list,
        int $minArity,
        bool $isVariadic = false,
        ?int $maxArity = null,
    ): self {
        $gotCount = count($list->rest());
        $fnName = sprintf('%s\\%s', $f->getNamespace(), $f->getName()->getName());

        return self::withLocation(
            sprintf(
                'Wrong number of arguments to function "%s". Got: %d. Expected: %s',
                $fnName,
                $gotCount,
                self::formatExpectedArity($minArity, $isVariadic, $maxArity),
            ),
            $list,
        );
    }

    public static function tooManyArgsProvided(
        GlobalVarNode $f,
        PersistentListInterface $list,
        int $minArity,
        int $maxArity,
    ): self {
        $gotCount = count($list->rest());
        $fnName = sprintf('%s\\%s', $f->getNamespace(), $f->getName()->getName());

        return self::withLocation(
            sprintf(
                'Wrong number of arguments to function "%s". Got: %d. Expected: %s',
                $fnName,
                $gotCount,
                self::formatExpectedArity($minArity, false, $maxArity),
            ),
            $list,
        );
    }

    public static function whenExpandingInlineFn(
        PersistentListInterface $list,
        GlobalVarNode $node,
        Exception $exception,
    ): self {
        throw self::withLocation(
            sprintf(
                'Error in expanding inline function of "%s\\%s": %s',
                $node->getNamespace(),
                $node->getName()->getName(),
                $exception->getMessage(),
            ),
            $list,
            $exception,
        );
    }

    public static function whenExpandingMacro(
        PersistentListInterface $list,
        GlobalVarNode $node,
        Exception $exception,
    ): self {
        throw self::withLocation(
            sprintf(
                'Error in expanding macro "%s\\%s": %s',
                $node->getNamespace(),
                $node->getName()->getName(),
                $exception->getMessage(),
            ),
            $list,
            $exception,
        );
    }

    private static function formatExpectedArity(int $minArity, bool $isVariadic, ?int $maxArity): string
    {
        if ($isVariadic) {
            return sprintf('at least %d', $minArity);
        }

        if ($maxArity === null || $minArity === $maxArity) {
            return (string) $minArity;
        }

        // For bounded arities (like if that takes 2 or 3 args)
        if ($maxArity === $minArity + 1) {
            return sprintf('%d or %d', $minArity, $maxArity);
        }

        return sprintf('%d to %d', $minArity, $maxArity);
    }

    /**
     * @param non-empty-array<string> $suggestions
     */
    private static function formatSuggestions(array $suggestions): string
    {
        $count = count($suggestions);

        if ($count === 1) {
            return sprintf("'%s'", $suggestions[0]);
        }

        if ($count === 2) {
            return sprintf("'%s' or '%s'", $suggestions[0], $suggestions[1]);
        }

        /** @var string $lastSuggestion */
        $lastSuggestion = array_pop($suggestions);
        $quotedSuggestions = array_map(static fn (string $s): string => sprintf("'%s'", $s), $suggestions);

        return implode(', ', $quotedSuggestions) . sprintf(", or '%s'", $lastSuggestion);
    }
}
