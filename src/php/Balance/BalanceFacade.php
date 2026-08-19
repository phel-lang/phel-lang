<?php

declare(strict_types=1);

namespace Phel\Balance;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Balance\Domain\BalanceResult;
use Phel\Balance\Domain\Exception\BalanceSourceException;
use Phel\Balance\Domain\RepairStrategy;

/**
 * @extends AbstractFacade<BalanceFactory>
 */
#[ServiceMap(method: 'getFactory', className: BalanceFactory::class)]
final class BalanceFacade extends AbstractFacade
{
    /**
     * Scans every `.phel` file under $paths for unbalanced delimiters. With
     * $fix, appends the missing closers to the files that can take them; the
     * files that cannot are reported and left untouched.
     *
     * @param list<string> $paths
     *
     * @throws BalanceSourceException when a listed directory cannot be walked
     */
    public function balance(array $paths, bool $fix = false, RepairStrategy $strategy = RepairStrategy::Append): BalanceResult
    {
        return $this->getFactory()
            ->createPathsBalancer()
            ->balance($paths, $fix, $strategy);
    }
}
