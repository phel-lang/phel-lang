<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding;

use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;

interface BindingValidatorInterface
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
    public function assertSupportedBinding(mixed $form): void;
}
