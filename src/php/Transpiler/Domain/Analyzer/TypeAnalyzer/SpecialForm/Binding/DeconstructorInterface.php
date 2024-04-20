<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;

interface DeconstructorInterface
{
    public function deconstruct(PersistentVectorInterface $form): array;
}
