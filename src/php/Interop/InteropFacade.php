<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractFacade;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Interop\Domain\ReadModel\Wrapper;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @method InteropFactory getFactory()
 */
final class InteropFacade extends AbstractFacade implements InteropFacadeInterface
{
    /**
     * @return list<Wrapper>
     */
    public function generateExportCode(): array
    {
        return $this->getFactory()
            ->createExportCodeGenerator()
            ->generateExportCode();
    }

    public function writeLocatedException(OutputInterface $output, CompilerException $e): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->writeLocatedException(
                $output,
                $e->getNestedException(),
                $e->getCodeSnippet(),
            );
    }

    public function writeStackTrace(OutputInterface $output, Throwable $e): void
    {
        $this->getFactory()
            ->getCommandFacade()
            ->writeStackTrace($output, $e);
    }
}
