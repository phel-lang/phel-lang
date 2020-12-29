<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding;

use Phel\Exceptions\AnalyzerException;
use Phel\Lang\AbstractType;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;

final class BindingValidator implements BindingValidatorInterface
{
    /**
     * Checks if a binding form is valid. If this is not the case an
     * AnalyzerException is thrown.
     *
     * @psalm-assert !null $form
     *
     * @param mixed $form The form to check
     *
     * @throws AnalyzerException
     */
    public function assertSupportedBinding($form): void
    {
        if (!$this->isSupportedBinding($form)) {
            $type = is_object($form) ? get_class($form) : gettype($form);

            if ($form instanceof AbstractType) {
                throw AnalyzerException::withLocation('Can not destructure ' . $type, $form);
            }

            throw new AnalyzerException('Can not destructure ' . $type);
        }
    }

    /**
     * Checks if a binding form is valid.
     *
     * @psalm-assert !null $form
     *
     * @param mixed $form The form to check
     */
    private function isSupportedBinding($form): bool
    {
        return $form instanceof Symbol
            || $form instanceof Tuple
            || $form instanceof Table
            || $form instanceof PhelArray;
    }
}
