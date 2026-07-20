<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

/**
 * @phpstan-type BindingTuple array{0: Symbol, 1: mixed}
 */
interface DeconstructorInterface
{
    /**
     * @param PersistentVectorInterface<mixed> $form
     *
     * @return list<BindingTuple>
     */
    public function deconstruct(PersistentVectorInterface $form): array;
}
