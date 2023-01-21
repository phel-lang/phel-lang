<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractFacade;
use Phel\Api\Transfer\NormalizedPhelFunction;

/**
 * @method ApiFactory getFactory()
 */
final class ApiFacade extends AbstractFacade implements ApiFacadeInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return array<string,list<NormalizedPhelFunction>>
     */
    public function getNormalizedGroupedFunctions(array $namespaces = []): array
    {
        return $this->getFactory()
            ->createPhelFnNormalizer()
            ->getNormalizedGroupedFunctions($namespaces);
    }
}
