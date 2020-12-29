<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol\Binding;

use Phel\Exceptions\AnalyzerException;

interface BindingValidatorInterface
{
    /**
     * Checks if a binding form is valid. If this is not the case an
     * AnalyzerException is thrown.
     *
     * @param mixed $form The form to check
     *
     * @throws AnalyzerException
     */
    public function assertSupportedBinding($form): void;
}
