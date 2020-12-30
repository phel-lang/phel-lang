<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Lang\AbstractType;

interface QuasiquoteTransformerInterface
{
    /**
     * @param AbstractType|string|float|int|bool|null $form The form to quasiqoute
     *
     * @return AbstractType|string|float|int|bool|null
     */
    public function transform($form);
}
