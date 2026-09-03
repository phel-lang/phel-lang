<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader;

use Phel\Lang\TypeInterface;

/**
 * @internal
 */
interface QuasiquoteTransformerInterface
{
    public function transform(TypeInterface|string|float|int|bool|null $form): TypeInterface|string|float|int|bool|null;
}
