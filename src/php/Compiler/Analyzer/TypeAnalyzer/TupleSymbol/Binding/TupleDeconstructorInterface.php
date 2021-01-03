<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding;

use Phel\Lang\Tuple;

interface TupleDeconstructorInterface
{
    public function deconstruct(Tuple $form): array;
}
