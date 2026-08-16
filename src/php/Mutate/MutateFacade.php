<?php

declare(strict_types=1);

namespace Phel\Mutate;

use Closure;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Mutate\Application\MutantWorkerSession;
use Phel\Mutate\Domain\Exception\BaselineFailedException;
use Phel\Mutate\Domain\Exception\WorkerFailedException;
use Phel\Mutate\Domain\Mutant;
use Phel\Mutate\Domain\MutantResult;
use Phel\Mutate\Domain\MutateOptions;
use Phel\Mutate\Domain\MutationPlan;
use Phel\Mutate\Domain\MutationReport;

/**
 * @extends AbstractFacade<MutateFactory>
 */
#[ServiceMap(method: 'getFactory', className: MutateFactory::class)]
final class MutateFacade extends AbstractFacade
{
    /**
     * Resolves what to mutate and what to test against, from the options and
     * the project configuration.
     */
    public function plan(MutateOptions $options): MutationPlan
    {
        return $this->getFactory()->createMutationPlanner()->plan($options);
    }

    /**
     * Every mutant the selected mutators produce over the plan's source files.
     *
     * @return list<Mutant>
     */
    public function generate(MutationPlan $plan, MutateOptions $options): array
    {
        return $this->getFactory()->createMutantGenerator($options)->generateFiles($plan->sourceFiles);
    }

    /**
     * Runs the baseline and every mutant in a worker subprocess.
     *
     * @param list<Mutant>                     $mutants
     * @param Closure(MutantResult): void|null $onResult
     *
     * @throws BaselineFailedException when the unmutated suite is red
     * @throws WorkerFailedException   when the worker cannot load the project
     */
    public function run(MutationPlan $plan, MutateOptions $options, array $mutants, ?Closure $onResult = null): MutationReport
    {
        return $this->getFactory()->createMutationRunner($plan, $options)->run($mutants, $onResult);
    }

    /**
     * The worker side of the protocol, for `phel _mutate-worker`.
     */
    public function createWorkerSession(): MutantWorkerSession
    {
        return $this->getFactory()->createWorkerSession();
    }
}
