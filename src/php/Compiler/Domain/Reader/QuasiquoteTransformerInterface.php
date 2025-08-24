<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel\Lang\TypeInterface;

interface QuasiquoteTransformerInterface
{
    /**
     * @param bool|float|int|string|TypeInterface|null $form The form to quasiqoute
     *
     * @return bool|float|int|string|TypeInterface|null
     */
    public function transform($form);
}
