<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractFacade;
use Phel\Api\Transfer\PhelFunction;

/**
 * @method ApiFactory getFactory()
 */
final class ApiFacade extends AbstractFacade implements ApiFacadeInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return list<PhelFunction>
     */
    public function getPhelFunctions(array $namespaces = []): array
    {
        return $this->getFactory()
            ->createPhelFnNormalizer()
            ->getPhelFunctions($namespaces);
    }
}
