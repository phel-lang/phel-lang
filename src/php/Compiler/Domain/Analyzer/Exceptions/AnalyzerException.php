<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use Exception;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Exceptions\ErrorCode;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeInterface;
use Phel\Printer\Printer;

use function count;
use function get_debug_type;
use function implode;
use function is_array;
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

        $e = self::withLocation($message, $type);
        $e->setErrorCode(ErrorCode::UNDEFINED_SYMBOL);

        return $e;
    }

    /**
     * Creates a type error exception with information about expected vs received type.
     *
     * @param array<string>|string $expectedTypes Expected type name(s)
     */
    public static function wrongArgumentType(
        string $context,
        string|array $expectedTypes,
        mixed $actualValue,
        TypeInterface $location,
    ): self {
        $expectedList = is_array($expectedTypes)
            ? implode(', ', $expectedTypes)
            : $expectedTypes;

        $actualType = self::formatTypeName($actualValue);

        $e = self::withLocation(
            sprintf('%s, got %s', $context . ' must be a ' . $expectedList, $actualType),
            $location,
        );
        $e->setErrorCode(ErrorCode::TYPE_ERROR);

        return $e;
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

        $e = self::withLocation(
            sprintf(
                'Wrong number of arguments to function "%s". Got: %d. Expected: %s',
                $fnName,
                $gotCount,
                self::formatExpectedArity($minArity, $isVariadic, $maxArity),
            ),
            $list,
        );
        $e->setErrorCode(ErrorCode::ARITY_ERROR);

        return $e;
    }

    public static function tooManyArgsProvided(
        GlobalVarNode $f,
        PersistentListInterface $list,
        int $minArity,
        int $maxArity,
    ): self {
        $gotCount = count($list->rest());
        $fnName = sprintf('%s\\%s', $f->getNamespace(), $f->getName()->getName());

        $e = self::withLocation(
            sprintf(
                'Wrong number of arguments to function "%s". Got: %d. Expected: %s',
                $fnName,
                $gotCount,
                self::formatExpectedArity($minArity, false, $maxArity),
            ),
            $list,
        );
        $e->setErrorCode(ErrorCode::ARITY_ERROR);

        return $e;
    }

    public static function whenExpandingInlineFn(
        PersistentListInterface $list,
        GlobalVarNode $node,
        Exception $exception,
    ): self {
        $e = self::withLocation(
            self::formatMacroExpansionError(
                'inline function',
                $node->getNamespace(),
                $node->getName()->getName(),
                $list,
                $exception->getMessage(),
            ),
            $list,
            $exception,
        );
        $e->setErrorCode(ErrorCode::INLINE_EXPANSION_ERROR);

        throw $e;
    }

    public static function whenExpandingMacro(
        PersistentListInterface $list,
        GlobalVarNode $node,
        Exception $exception,
    ): self {
        $e = self::withLocation(
            self::formatMacroExpansionError(
                'macro',
                $node->getNamespace(),
                $node->getName()->getName(),
                $list,
                $exception->getMessage(),
            ),
            $list,
            $exception,
        );
        $e->setErrorCode(ErrorCode::MACRO_EXPANSION_ERROR);

        throw $e;
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

    private static function formatMacroExpansionError(
        string $type,
        string $namespace,
        string $name,
        PersistentListInterface $form,
        string $causeMessage,
    ): string {
        $formString = Printer::readable()->print($form);

        return sprintf(
            "Error in expanding %s \"%s\\%s\"\n  Expanding: %s\n  Cause: %s",
            $type,
            $namespace,
            $name,
            $formString,
            $causeMessage,
        );
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

    /**
     * Formats a type name for display in error messages.
     * Strips namespace prefixes for Phel types to make messages more readable.
     */
    private static function formatTypeName(mixed $value): string
    {
        $type = get_debug_type($value);

        // Strip Phel\Lang\ prefix for cleaner display
        if (str_starts_with($type, 'Phel\\Lang\\')) {
            return substr($type, 10);
        }

        // Strip Phel\Lang\Collections\ prefix
        if (str_starts_with($type, 'Phel\\Lang\\Collections\\')) {
            $parts = explode('\\', $type);

            return end($parts);
        }

        return $type;
    }
}
