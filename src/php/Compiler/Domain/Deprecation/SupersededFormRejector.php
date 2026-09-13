<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Deprecation;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function array_keys;

/**
 * Rejects a special form Phel already says another way, written in source.
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
 * The forms stay: they remain the compiler's own target, because `(new \C 1)`
 * becomes `(php/new \C 1)` and `(binding …)` expands to `set-var` (ADR 0007).
 * What goes is the user's ability to *write* them.
 *
 * That distinction is why this runs in the reader rather than the analyzer.
 * The reader turns typed characters into forms, so everything it produces was
 * typed by somebody; macro output is built during analysis and never passes
 * through here. Placing the check in the analyzer meant guessing which of two
 * forms a human wrote, and every available signal was wrong somewhere: a path
 * check on the expansion origin fails inside a PHAR, where the bundled stdlib
 * lives under `phar://`, and treating an absent location as compiler-generated
 * fails under nested expansion, where the outer expansion stamps a location
 * onto the inner macro's freshly built head.
 *
 * A quasiquote is already lowered when this runs, so a macro template reads as
 * `(apply list (concat (list (quote php/new)) …))`: the name survives as
 * quoted data, never as a list head, and a template keeps compiling. A plain
 * `quote` is skipped outright, because `'(php/new \C)` is data.
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
     * @throws AnalyzerException
     */
    public function rejectIfWritten(mixed $form): void
    {
        if ($form instanceof PersistentListInterface) {
            $head = $form->first();

            if ($head instanceof Symbol) {
                if ($head->getFullName() === Symbol::NAME_QUOTE) {
                    return;
                }

                $this->rejectHead($form, $head->getFullName());
            }
        }

        if (is_iterable($form)) {
            foreach ($form as $key => $value) {
                $this->rejectIfWritten($key);
                $this->rejectIfWritten($value);
            }
        }
    }

    /**
     * @param PersistentListInterface<mixed> $form
     *
     * @throws AnalyzerException
     */
    private function rejectHead(PersistentListInterface $form, string $name): void
    {
        $superseded = self::SUPERSEDED[$name] ?? null;
        if ($superseded === null) {
            return;
        }

        [$purpose, $replacement] = $superseded;

        throw AnalyzerException::supersededForm($form, $name, $purpose, $replacement);
    }
}
