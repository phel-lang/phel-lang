<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

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
            return;
        }

        $type = get_debug_type($form);

        if ($form instanceof TypeInterface) {
            throw AnalyzerException::withLocation('Cannot destructure ' . $type, $form);
        }

        throw new AnalyzerException('Cannot destructure ' . $type);
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
