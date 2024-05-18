<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;

interface BindingValidatorInterface
{
    /**
     * Checks if a binding form is valid. If this is not the case an
     * AnalyzerException is thrown.
     *
     * @psalm-assert !null $form
     *
     * @throws AnalyzerException
     */
    public function assertSupportedBinding(mixed $form): void;
}
