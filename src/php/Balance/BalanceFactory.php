<?php

declare(strict_types=1);

namespace Phel\Balance;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Balance\Application\BoundaryRepairer;
use Phel\Balance\Application\DelimiterRepairer;
use Phel\Balance\Application\DelimiterScanner;
use Phel\Balance\Application\PathsBalancer;
use Phel\Balance\Application\PhelFileCollector;
use Phel\Balance\Application\RepairSearch;
use Phel\Balance\Application\RepairValidator;
use Phel\Balance\Application\UnexpectedCloserRepairer;
use Phel\Balance\Domain\FileCollectorInterface;
use Phel\Balance\Domain\FileIoInterface;
use Phel\Balance\Infrastructure\IO\SystemFileIo;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

/**
 * @extends AbstractFactory<BalanceConfig>
 *
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: BalanceConfig::class)]
final class BalanceFactory extends AbstractFactory
{
    public function createPathsBalancer(): PathsBalancer
    {
        $scanner = $this->createDelimiterScanner();
        $validator = new RepairValidator($this->getCompilerFacade(), $scanner);

        return new PathsBalancer(
            $this->createPhelFileCollector(),
            $scanner,
            $this->createDelimiterRepairer(),
            $this->createFileIo(),
            new BoundaryRepairer(),
            new UnexpectedCloserRepairer(),
            $validator,
            new RepairSearch($this->getCompilerFacade(), $scanner, $validator),
        );
    }

    public function createDelimiterScanner(): DelimiterScanner
    {
        return new DelimiterScanner($this->getCompilerFacade());
    }

    public function createDelimiterRepairer(): DelimiterRepairer
    {
        return new DelimiterRepairer();
    }

    public function createPhelFileCollector(): FileCollectorInterface
    {
        return new PhelFileCollector();
    }

    public function createFileIo(): FileIoInterface
    {
        return new SystemFileIo();
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(CompilerFacadeInterface::class);
    }

    public function getCommandFacade(): CommandFacadeInterface
    {
        return $this->getProvidedDependency(CommandFacadeInterface::class);
    }
}
