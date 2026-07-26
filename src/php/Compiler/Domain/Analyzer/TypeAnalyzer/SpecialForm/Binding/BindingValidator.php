<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

/**
 * @internal
 */
final class BindingValidator implements BindingValidatorInterface
{
    /**
     * Checks if a binding form is valid. If this is not the case an
     * AnalyzerException is thrown.
     *
     * @psalm-assert !null $form
     *
     * @throws AnalyzerException
     */
    public function assertSupportedBinding(mixed $form): void
    {
        if ($this->isSupportedBinding($form)) {
            $this->assertUnqualifiedName($form);

            return;
        }

        $type = get_debug_type($form);

        if ($form instanceof TypeInterface) {
            throw AnalyzerException::withLocation('Cannot destructure ' . $type, $form);
        }

        throw new AnalyzerException('Cannot destructure ' . $type);
    }

    /**
     * A binding form introduces a local, and locals are always bare names: a
     * reference carrying a namespace resolves to the global definition, so a
     * qualified binding name would bind a value nothing can read back. It
     * mostly reaches the analyzer through a quasiquote, which qualifies any
     * symbol that resolves globally, so `` `(let [count 0] count) `` arrives
     * as `(let [phel.core/count 0] phel.core/count)`. Reject it here instead
     * of letting the body pick up the core function.
     *
     * @throws AnalyzerException
     */
    private function assertUnqualifiedName(mixed $form): void
    {
        if (!$form instanceof Symbol || $form->getNamespace() === null) {
            return;
        }

        throw AnalyzerException::withLocation(
            "Can't bind qualified name: " . $form->getFullName()
            . '. Use a bare name, or `' . $form->getName() . '#` for an auto-gensym inside a quasiquote.',
            $form,
        );
    }

    /**
     * Checks if a binding form is valid.
     *
     * @psalm-assert !null $form
     */
    private function isSupportedBinding(mixed $form): bool
    {
        return $form instanceof Symbol
            || $form instanceof PersistentVectorInterface
            || $form instanceof PersistentMapInterface;
    }
}
