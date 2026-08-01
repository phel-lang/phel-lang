<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use function in_array;
use function strlen;

/**
 * How a symbol has to be spelled to name a PHP class member, `\Foo/member`.
 *
 * Purely lexical, and the single statement of the rule: call position
 * (`AnalyzePersistentList`), value position (`QualifiedMemberExpander`) and
 * `phel.core/set!` all decide the same way, so `(\Foo/m 1)`, `\Foo/CONST` and
 * `(set! \Foo/slot v)` cannot disagree about what counts as a class. Nothing
 * here loads a class; `PhpClassLike` answers existence.
 *
 * @internal
 */
final class QualifiedMemberSyntax
{
    /**
     * A namespace part naming a PHP class rather than a Phel namespace. Phel
     * namespaces are lower case by convention, so an upper-case first
     * character is the signal, as is the leading `\` of an absolute name.
     * `php` is the host-function prefix, never a class.
     */
    public static function isClassReference(?string $namespace): bool
    {
        if (in_array($namespace, [null, '', 'php'], true)) {
            return false;
        }

        return $namespace[0] === '\\'
            || ($namespace[0] >= 'A' && $namespace[0] <= 'Z');
    }

    /**
     * A name part PHP could parse as a member. Only the first character is
     * checked, which is what keeps a Phel name PHP cannot spell (`*x*`, `-`)
     * out of the member readings.
     */
    public static function isMemberName(string $name): bool
    {
        return $name !== '' && self::isIdentifierStartChar($name[0]);
    }

    /**
     * A name part naming a static property, `$prop`. PHP files constants and
     * static properties separately and only the sigil tells them apart, so the
     * bare name stays the constant it has always been.
     */
    public static function isStaticPropertyName(string $name): bool
    {
        return strlen($name) > 1
            && $name[0] === '$'
            && self::isIdentifierStartChar($name[1]);
    }

    public static function isIdentifierStartChar(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z')
            || ($char >= 'A' && $char <= 'Z')
            || $char === '_';
    }
}
