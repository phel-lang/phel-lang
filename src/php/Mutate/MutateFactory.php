<?php

declare(strict_types=1);

namespace Phel\Mutate;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Mutate\Application\MutantGenerator;
use Phel\Mutate\Application\MutantWorker;
use Phel\Mutate\Application\MutantWorkerSession;
use Phel\Mutate\Application\MutationPlanner;
use Phel\Mutate\Application\MutationRunner;
use Phel\Mutate\Application\ProjectWarmer;
use Phel\Mutate\Domain\MutateOptions;
use Phel\Mutate\Domain\MutationPlan;
use Phel\Mutate\Domain\Mutator\MutatorRegistry;
use Phel\Mutate\Infrastructure\Command\MutateWorkerCommand;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Process\CpuCountDetector;
use Phel\Shared\Process\GitChangedFiles;
use Phel\Shared\Process\PhelBinaryLocator;

use const PHP_BINARY;

/**
 * @extends AbstractFactory<MutateConfig>
 *
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: MutateConfig::class)]
final class MutateFactory extends AbstractFactory
{
    public function createMutationPlanner(): MutationPlanner
    {
        return new MutationPlanner($this->getRunFacade(), $this->getCommandFacade(), new GitChangedFiles());
    }

    public function createCpuCountDetector(): CpuCountDetector
    {
        return new CpuCountDetector();
    }

    public function createMutantGenerator(MutateOptions $options): MutantGenerator
    {
        return new MutantGenerator($this->getCompilerFacade(), MutatorRegistry::select($options->mutators));
    }

    public function createMutationRunner(MutationPlan $plan, MutateOptions $options): MutationRunner
    {
        $command = [PHP_BINARY, PhelBinaryLocator::locate(), MutateWorkerCommand::COMMAND_NAME];

        return new MutationRunner(
            static fn(): MutantWorker => MutantWorker::spawn($command),
            $plan->loadOrder,
            $plan->testNamespaces,
            $options->timeoutFactor,
            $options->workers,
        );
    }

    public function createProjectWarmer(): ProjectWarmer
    {
        return new ProjectWarmer($this->getRunFacade());
    }

    public function createWorkerSession(): MutantWorkerSession
    {
        return new MutantWorkerSession($this->getRunFacade(), $this->getCompilerFacade());
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(CompilerFacadeInterface::class);
    }

    public function getRunFacade(): RunFacadeInterface
    {
        return $this->getProvidedDependency(RunFacadeInterface::class);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(CommandFacadeInterface::class);
    }
}
