<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Deprecation;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function array_keys;

/**
 * Detects a list head that is a special form Phel already says another way.
 *
 * `php/` marks host access: reaching a PHP function, a PHP array, or a PHP
 * reference, none of which Phel has a word for. It is not a second spelling
 * for something Phel already spells the Clojure way, so `php/new`, `php/->`
 * and `php/::` give way to `(new \C …)`, `(.m obj …)` and `(\C/m …)`.
 * `set-var` is here for the same reason: it does what `alter-var-root` does,
 * under a name that reads like Clojure's `set!`
 * (https://github.com/phel-lang/phel-lang/issues/2877,
 * https://github.com/phel-lang/phel-lang/issues/2888).
 *
 * Deprecated through 1.x's predecessors and rejected as source from 1.0.0.
 * They remain the compiler's own target: `(new \C 1)` becomes `(php/new \C 1)`
 * and `(binding …)` expands to `set-var`, so the forms cannot be deleted
 * (ADR 0007). What goes is the user's ability to write them, which is why the
 * check below turns on whether the head carries a source location.
 *
 * Rejection is unconditional, unlike the deprecation notice it replaces: there
 * is no flag to turn it off, and only the bundled stdlib is exempt.
 *
 * Must run on the list as written, before the analyzer desugars `(.m obj)`
 * into `(php/-> obj (m))`, or every shorthand would warn about the form it
 * expands to.
 *
 * @internal
 */
final class SupersededFormRejector
{
    /** @var array<string, array{string, string}> form => [purpose, replacement] */
    private const array SUPERSEDED = [
        Symbol::NAME_PHP_NEW => [
            'constructing a PHP object',
            '"(new \Foo arg)" or "(\Foo. arg)"',
        ],
        Symbol::NAME_PHP_OBJECT_CALL => [
            'instance members',
            '"(.method obj arg)" and "(.-field obj)"',
        ],
        Symbol::NAME_PHP_OBJECT_STATIC_CALL => [
            'static members',
            '"(\Foo/method arg)" and "\Foo/CONST"',
        ],
        Symbol::NAME_SET_VAR => [
            "a var's root value",
            '"(alter-var-root (var v) f)", or "(set! v x)" for the current binding frame',
        ],
    ];

    /**
     * The forms this rejects, for the spec test that keeps
     * `docs/spec/language-surface.md` from drifting away from it.
     *
     * @return list<string>
     */
    public static function supersededFormNames(): array
    {
        return array_keys(self::SUPERSEDED);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     *
     * @throws AnalyzerException
     */
    public function rejectIfWritten(PersistentListInterface $list): void
    {
        $head = $list->first();
        if (!$head instanceof Symbol) {
            return;
        }

        // An unlocated head is one the analyzer synthesized rather than one
        // anybody wrote: `QualifiedMemberExpander` turning `\C/CONST` into a
        // `php/::` form is the case that matters.
        $location = $head->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        $superseded = self::SUPERSEDED[$head->getFullName()] ?? null;
        if ($superseded === null) {
            return;
        }

        // A macro-expanded form is located at the call site and carries the
        // place it was *written* as its expansion origin. That origin is the
        // question here: `binding` builds `(set-var v e)` and `set!` builds a
        // `php/::`, both written in `src/phel`, and both must keep working
        // when a user calls the macro. A macro of the user's own that emits
        // one of these is theirs to fix, and its origin says so.
        $origin = $location->getExpansionOrigin() ?? $location;
        if (DeprecationWarnings::isBundledStdlibSource($origin->getFile())) {
            return;
        }

        [$purpose, $replacement] = $superseded;

        throw AnalyzerException::supersededForm($list, $head->getFullName(), $purpose, $replacement);
    }
}
