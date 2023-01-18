<?php

declare(strict_types=1);

namespace Phel\Internal;

use Gacela\Framework\AbstractFacade;
use Phel\Internal\Transfer\NormalizedPhelFunction;

/**
 * @method InternalFactory getFactory()
 */
final class InternalFacade extends AbstractFacade implements InternalFacadeInterface
{
    /**
     * @return array<string,list<NormalizedPhelFunction>>
     */
    public function getNormalizedGroupedFunctions(): array
    {
        return $this->getFactory()
            ->createPhelFnNormalizer()
            ->getNormalizedGroupedFunctions();
    }
}
