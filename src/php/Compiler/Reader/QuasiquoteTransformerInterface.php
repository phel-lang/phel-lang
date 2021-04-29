<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader;

use Phel\Lang\TypeInterface;

interface QuasiquoteTransformerInterface
{
    /**
     * @param TypeInterface|string|float|int|bool|null $form The form to quasiqoute
     *
     * @return TypeInterface|string|float|int|bool|null
     */
    public function transform($form);
}
