<?php

declare(strict_types=1);

namespace Phel\Nrepl;

use Gacela\Framework\AbstractFactory;
use Phel\Nrepl\Application\Op\CloneOp;
use Phel\Nrepl\Application\Op\CloseOp;
use Phel\Nrepl\Application\Op\CompletionsOp;
use Phel\Nrepl\Application\Op\DescribeOp;
use Phel\Nrepl\Application\Op\EvalOp;
use Phel\Nrepl\Application\Op\EvalResultResponder;
use Phel\Nrepl\Application\Op\InterruptOp;
use Phel\Nrepl\Application\Op\LoadFileOp;
use Phel\Nrepl\Application\Op\LookupOp;
use Phel\Nrepl\Application\Op\ReloadOp;
use Phel\Nrepl\Application\Op\RunTestsOp;
use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Nrepl\Infrastructure\NreplSocketServer;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;
use Phel\Shared\Printer\Printer;
use Phel\Shared\Printer\PrinterInterface;

/**
 * @extends AbstractFactory<NreplConfig>
 */
final class NreplFactory extends AbstractFactory
{
    public function createSocketServer(
        int $port,
        string $host,
        ?callable $logger = null,
    ): NreplSocketServer {
        return new NreplSocketServer(
            $this->createOpDispatcher(),
            $port,
            $host,
            $logger,
        );
    }

    public function createOpDispatcher(): OpDispatcher
    {
        $sessions = $this->createSessionRegistry();
        $responder = new EvalResultResponder($this->createPrinter(), $sessions);
        $dispatcher = new OpDispatcher();

        $dispatcher->register(new CloneOp($sessions));
        $dispatcher->register(new CloseOp($sessions));
        $dispatcher->register(new EvalOp($this->getRunFacade(), $responder));
        $dispatcher->register(new LoadFileOp($this->getRunFacade(), $responder));
        $dispatcher->register(new ReloadOp($this->getRunFacade(), $responder));
        $dispatcher->register(new RunTestsOp($this->getRunFacade(), $responder));
        $dispatcher->register(new InterruptOp());
        $dispatcher->register(new CompletionsOp($this->getApiFacade()));
        $dispatcher->register(new LookupOp($this->getApiFacade(), 'lookup', $sessions));
        $dispatcher->register(new LookupOp($this->getApiFacade(), 'info', $sessions));
        $dispatcher->register(new LookupOp($this->getApiFacade(), 'eldoc', $sessions));

        // DescribeOp reports the dispatcher's known ops, so it must be
        // registered last (after every other handler); registering it earlier
        // would omit later ops from the describe response.
        $dispatcher->register(new DescribeOp($dispatcher, $this->getRunFacade()));

        return $dispatcher;
    }

    public function createSessionRegistry(): SessionRegistry
    {
        return new SessionRegistry();
    }

    public function createPrinter(): PrinterInterface
    {
        return Printer::readable();
    }

    public function getRunFacade(): RunFacadeInterface
    {
        /** @var RunFacadeInterface $facade */
        $facade = $this->getProvidedDependency(NreplProvider::FACADE_RUN);

        return $facade;
    }

    public function getApiFacade(): ApiFacadeInterface
    {
        /** @var ApiFacadeInterface $facade */
        $facade = $this->getProvidedDependency(NreplProvider::FACADE_API);

        return $facade;
    }
}
