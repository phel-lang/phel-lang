<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Deprecation;

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
 * and `php/::` are deprecated in favour of `(new \C …)`, `(.m obj …)` and
 * `(\C/m …)`. `set-var` is here for the same reason: it does what
 * `alter-var-root` does, under a name that reads like Clojure's `set!`
 * (https://github.com/phel-lang/phel-lang/issues/2877,
 * https://github.com/phel-lang/phel-lang/issues/2888).
 *
 * Detection only: the enabled gate, the bundled-stdlib suppression and the
 * macro-expansion attribution all belong to {@see DeprecationWarnings}, which
 * is why this class is stateless.
 *
 * Must run on the list as written, before the analyzer desugars `(.m obj)`
 * into `(php/-> obj (m))`, or every shorthand would warn about the form it
 * expands to.
 *
 * @internal
 */
final class SupersededFormDeprecator
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
     * The forms this deprecates, for the spec test that keeps
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
     */
    public function maybeWarn(PersistentListInterface $list): void
    {
        if (!DeprecationWarnings::isDetecting()) {
            return;
        }

        $head = $list->first();
        if (!$head instanceof Symbol) {
            return;
        }

        // An unlocated head is one the analyzer synthesized, not one the user
        // wrote — `QualifiedMemberExpander` turning `\C/CONST` into a `php/::`
        // form is the case that matters.
        $location = $head->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        $superseded = self::SUPERSEDED[$head->getFullName()] ?? null;
        if ($superseded === null) {
            return;
        }

        [$purpose, $replacement] = $superseded;

        DeprecationWarnings::warnSyntax(
            '"' . $head->getFullName() . '"',
            $purpose,
            $replacement,
            $location,
        );
    }
}
