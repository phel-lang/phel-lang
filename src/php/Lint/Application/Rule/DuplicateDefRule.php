<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Api\Diagnostic;

use function count;
use function in_array;
use function sprintf;

/**
 * Flags a top-level symbol defined twice in the same file, e.g.
 *
 * ```phel
 * (defn handle [x] x)
 * (defn handle [x] (inc x)) ; the first definition is dead code
 * ```
 *
 * The analyzer raises on this too, but only once the namespace has been
 * evaluated, so a compile-only pass (which is all the linter does) never
 * sees it. This rule works purely off the file's own read forms, so the
 * verdict does not depend on what the linting process happens to have
 * loaded.
 *
 * A forward `(declare foo)` followed by the real definition is the normal
 * Lisp idiom and stays clean: `declare` only reserves the name.
 */
final readonly class DuplicateDefRule implements LintRuleInterface
{
    /**
     * Heads that introduce exactly one new global, named by the form's
     * second element. `defonce` is excluded on purpose (its whole point is
     * to tolerate re-evaluation) and so is `defmethod`, which extends an
     * existing `defmulti` rather than defining a new global.
     */
    private const array DEFINING_HEADS = [
        'def',
        'def-',
        'defn',
        'defn-',
        'defmacro',
        'defmacro-',
        'definterface',
        'defstruct',
        'defexception',
        'defenum',
        'defprotocol',
        'defrecord',
        'deftype',
        'defmulti',
    ];

    public function code(): string
    {
        return RuleRegistry::DUPLICATE_DEF;
    }

    public function apply(FileAnalysis $analysis): array
    {
        /** @var list<Diagnostic> $result */
        $result = [];
        /** @var array<string, true> $defined */
        $defined = [];

        foreach ($analysis->forms as $form) {
            if (!$form instanceof PersistentListInterface) {
                continue;
            }

            if (count($form) < 2) {
                continue;
            }

            $head = $form->get(0);
            if (!$head instanceof Symbol) {
                continue;
            }

            if (!in_array($head->getName(), self::DEFINING_HEADS, true)) {
                continue;
            }

            $name = $form->get(1);
            if (!$name instanceof Symbol) {
                continue;
            }

            $key = $name->getName();
            if (isset($defined[$key])) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    sprintf('Duplicate definition: %s is already defined in this file.', $key),
                    $analysis->uri,
                    $name,
                );

                continue;
            }

            $defined[$key] = true;
        }

        return $result;
    }
}
