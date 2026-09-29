<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Domain\CoreFunctionNamesInterface;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Binding\IterationHead;
use Phel\Shared\LintRuleCodes;

use function count;
use function in_array;
use function sprintf;

/**
 * Flags a local binding named after a public `phel.core` function: inside its
 * scope the local wins, so `(let [inc (fn [x] 99)] (inc 1))` silently calls
 * the local. Only plain symbols in binding position count; names bound by
 * destructuring are left alone, like `phel/shadowed-binding` does.
 *
 * @internal
 */
final readonly class ShadowedCoreFnRule implements LintRuleInterface
{
    private const array LET_FORMS = ['let', 'loop', 'if-let', 'when-let'];

    private const array FN_FORMS = ['fn', 'defn', 'defn-', 'defmacro', 'defmacro-'];

    public function __construct(
        private CoreFunctionNamesInterface $coreFunctions,
    ) {}

    public function code(): string
    {
        return LintRuleCodes::SHADOWED_CORE_FN;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $result = [];
        foreach ($analysis->forms as $form) {
            $this->walk($form, $analysis->uri, $result);
        }

        return $result;
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function walk(mixed $form, string $uri, array &$result): void
    {
        if ($form instanceof PersistentListInterface && count($form) > 0) {
            $head = $form->get(0);
            if ($head instanceof Symbol) {
                $name = $head->getName();
                if ($name === Symbol::NAME_QUOTE) {
                    return;
                }

                $this->checkBindings($name, $form, $uri, $result);
            }

            foreach ($form as $child) {
                $this->walk($child, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                $this->walk($child, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                $this->walk($k, $uri, $result);
                $this->walk($v, $uri, $result);
            }
        }
    }

    /**
     * @param PersistentListInterface<mixed> $form
     * @param list<Diagnostic>               $result
     */
    private function checkBindings(string $formName, PersistentListInterface $form, string $uri, array &$result): void
    {
        if (in_array($formName, self::FN_FORMS, true)) {
            foreach (FnParamVectors::of($form) as $params) {
                foreach ($params as $param) {
                    $this->check($param, $uri, $result);
                }
            }

            return;
        }

        if (count($form) < 2) {
            return;
        }

        $head = $form->get(1);
        if (!$head instanceof PersistentVectorInterface) {
            return;
        }

        if (in_array($formName, self::LET_FORMS, true)) {
            $size = count($head);
            for ($i = 0; $i < $size; $i += 2) {
                $this->check($head->get($i), $uri, $result);
            }

            return;
        }

        if (IterationHead::isIterationForm($formName)) {
            foreach (IterationHead::entries($formName, $head) as $entry) {
                $this->check($entry['binding'], $uri, $result);
            }
        }
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function check(mixed $binding, string $uri, array &$result): void
    {
        if (!$binding instanceof Symbol || $binding->getNamespace() !== null) {
            return;
        }

        $name = $binding->getName();
        if (!$this->coreFunctions->contains($name)) {
            return;
        }

        $result[] = DiagnosticBuilder::fromForm(
            $this->code(),
            sprintf("Binding '%s' shadows the core function 'phel.core/%s'.", $name, $name),
            $uri,
            $binding,
        );
    }
}
