<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Exceptions;

use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\PhelType;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Printer\Printer;
use Throwable;

use function count;
use function get_debug_type;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;

/**
 * @internal
 */
final class AnalyzerException extends AbstractLocatedException
{
    public static function withLocation(
        string $message,
        TypeInterface $type,
        ?Throwable $nested = null,
        ?ErrorCode $errorCode = null,
    ): self {
        $e = new self(
            $message,
            $type->getStartLocation(),
            $type->getEndLocation(),
            $nested,
        );

        if ($errorCode instanceof ErrorCode) {
            $e->setErrorCode($errorCode);
        }

        return $e;
    }

    /**
     * A form Phel already says another way, written in source. It stays the
     * compiler's own target, so the message is about the spelling rather than
     * the capability: everything these four did is still reachable (#2877,
     * #2888, ADR 0007).
     *
     * @param PersistentListInterface<mixed> $list
     */
    public static function supersededForm(
        PersistentListInterface $list,
        string $name,
        string $purpose,
        string $replacement,
    ): self {
        return self::withLocation(
            sprintf('"%s" is no longer valid source for %s. Use %s instead.', $name, $purpose, $replacement),
            $list,
            errorCode: ErrorCode::SUPERSEDED_FORM,
        );
    }

    /**
     * A special form given too few arguments. It names the form and the shape
     * it wanted, because "wrong number of arguments" without the shape sends
     * the reader to the documentation for something they nearly wrote.
     *
     * Reading an argument before checking the count let the list's own
     * out-of-bounds error reach the user instead, under a runtime code, with
     * no snippet and no caret (#3297).
     *
     * @param PersistentListInterface<mixed> $list
     */
    public static function wrongArity(PersistentListInterface $list, string $usage): self
    {
        $first = $list->first();
        $name = $first instanceof Symbol ? $first->getFullName() : 'form';

        return self::withLocation(
            sprintf("Wrong number of arguments for '%s. Usage: %s", $name, $usage),
            $list,
            errorCode: ErrorCode::ARITY_ERROR,
        );
    }

    /**
     * The same error raised on a form that carries no source location, a bare
     * scalar in a binding vector for one. The code is what the reader is left
     * with, so it still has to be set.
     */
    public static function withoutLocation(string $message, ErrorCode $errorCode): self
    {
        $e = new self($message);
        $e->setErrorCode($errorCode);

        return $e;
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

        return self::withLocation($message, $type, errorCode: ErrorCode::UNDEFINED_SYMBOL);
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

        return self::withLocation(
            sprintf('%s, got %s', $context . ' must be a ' . $expectedList, $actualType),
            $location,
            errorCode: ErrorCode::TYPE_ERROR,
        );
    }

    public static function notCallable(
        string $displayValue,
        string $typeName,
        TypeInterface $location,
        string $hint = '',
    ): self {
        $message = sprintf('Value %s of type %s is not callable.', $displayValue, $typeName);

        if ($hint !== '') {
            $message .= ' ' . $hint;
        }

        return self::withLocation($message, $location, errorCode: ErrorCode::NOT_CALLABLE);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
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
            errorCode: ErrorCode::ARITY_ERROR,
        );
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
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
            errorCode: ErrorCode::ARITY_ERROR,
        );
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public static function whenExpandingInlineFn(
        PersistentListInterface $list,
        GlobalVarNode $node,
        Throwable $exception,
    ): self {
        throw self::withLocation(
            self::formatMacroExpansionError(
                'inline function',
                $node->getNamespace(),
                $node->getName()->getName(),
                $list,
                $exception->getMessage(),
                self::definitionLocation($node),
            ),
            $list,
            $exception,
            ErrorCode::INLINE_EXPANSION_ERROR,
        );
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public static function whenExpandingMacro(
        PersistentListInterface $list,
        GlobalVarNode $node,
        Throwable $exception,
    ): self {
        throw self::withLocation(
            self::formatMacroExpansionError(
                'macro',
                $node->getNamespace(),
                $node->getName()->getName(),
                $list,
                $exception->getMessage(),
                self::definitionLocation($node),
            ),
            $list,
            $exception,
            ErrorCode::MACRO_EXPANSION_ERROR,
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
     * @param PersistentListInterface<mixed> $form
     */
    private static function formatMacroExpansionError(
        string $type,
        string $namespace,
        string $name,
        PersistentListInterface $form,
        string $causeMessage,
        ?string $definedAt = null,
    ): string {
        $formString = Printer::readable()->print($form);

        $message = sprintf(
            "Error in expanding %s \"%s\\%s\"\n  Expanding: %s\n  Cause: %s",
            $type,
            $namespace,
            $name,
            $formString,
            $causeMessage,
        );

        if ($definedAt !== null) {
            $message .= "\n  Defined: " . $definedAt;
        }

        return $message;
    }

    /**
     * Returns `<file>:<line>` where the macro/inline fn was defined, read from
     * the `:start-location` metadata attached by `def`, or null when absent.
     */
    private static function definitionLocation(GlobalVarNode $node): ?string
    {
        $startLocation = $node->getMeta()[Keyword::create('start-location')] ?? null;
        if (!$startLocation instanceof PersistentMapInterface) {
            return null;
        }

        $file = $startLocation[Keyword::create('file')] ?? null;
        $line = $startLocation[Keyword::create('line')] ?? null;

        if (!is_string($file) || !is_int($line)) {
            return null;
        }

        return sprintf('%s:%d', $file, $line);
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

        $lastSuggestion = array_pop($suggestions);
        $quotedSuggestions = array_map(static fn(string $s): string => sprintf("'%s'", $s), $suggestions);

        return implode(', ', $quotedSuggestions) . sprintf(", or '%s'", $lastSuggestion);
    }

    /**
     * Names the value the way the language names it, so a message agrees with
     * what `(type value)` returns for the same value. A host object is the one
     * place the class beats the Phel answer: `php/object` says nothing a reader
     * can act on, and the class name says which object arrived.
     */
    private static function formatTypeName(mixed $value): string
    {
        $type = PhelType::nameOf($value);

        if ($type === 'php/object' || $type === PhelType::UNKNOWN) {
            return get_debug_type($value);
        }

        return $type;
    }
}
