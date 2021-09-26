<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractFacade;

/**
 * @deprecated without replacement
 *
 * @method RuntimeFactory getFactory()
 */
final class RuntimeFacade extends AbstractFacade implements RuntimeFacadeInterface
{
    public function getRuntime(): RuntimeInterface
    {
        return $this->getFactory()->getRuntime();
    }
}
