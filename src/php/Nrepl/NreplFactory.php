<?php

declare(strict_types=1);

namespace Phel\Nrepl;

use Gacela\Framework\AbstractFactory;
use Phel\Api\ApiFacade;
use Phel\Nrepl\Application\Op\CloneOp;
use Phel\Nrepl\Application\Op\CloseOp;
use Phel\Nrepl\Application\Op\CompletionsOp;
use Phel\Nrepl\Application\Op\DescribeOp;
use Phel\Nrepl\Application\Op\EvalOp;
use Phel\Nrepl\Application\Op\InterruptOp;
use Phel\Nrepl\Application\Op\LoadFileOp;
use Phel\Nrepl\Application\Op\LookupOp;
use Phel\Nrepl\Domain\Bencode\BencodeDecoder;
use Phel\Nrepl\Domain\Bencode\BencodeEncoder;
use Phel\Nrepl\Domain\Op\OpDispatcher;
use Phel\Nrepl\Domain\Session\SessionRegistry;
use Phel\Nrepl\Infrastructure\NreplSocketServer;
use Phel\Printer\Printer;
use Phel\Printer\PrinterInterface;
use Phel\Run\RunFacade;

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
        $dispatcher = new OpDispatcher();

        $dispatcher->register(new CloneOp($sessions));
        $dispatcher->register(new CloseOp($sessions));
        $dispatcher->register(new EvalOp(
            $this->getRunFacade(),
            $this->createPrinter(),
            $sessions,
        ));
        $dispatcher->register(new LoadFileOp(
            $this->getRunFacade(),
            $this->createPrinter(),
            $sessions,
        ));
        $dispatcher->register(new InterruptOp());
        $dispatcher->register(new CompletionsOp($this->getApiFacade()));
        $dispatcher->register(new LookupOp($this->getApiFacade(), 'lookup'));
        $dispatcher->register(new LookupOp($this->getApiFacade(), 'info'));
        $dispatcher->register(new LookupOp($this->getApiFacade(), 'eldoc'));

        // Describe needs to inspect known ops, register last.
        $dispatcher->register(new DescribeOp($dispatcher, $this->getRunFacade()));

        return $dispatcher;
    }

    public function createSessionRegistry(): SessionRegistry
    {
        return new SessionRegistry();
    }

    public function createBencodeEncoder(): BencodeEncoder
    {
        return new BencodeEncoder();
    }

    public function createBencodeDecoder(): BencodeDecoder
    {
        return new BencodeDecoder();
    }

    public function createPrinter(): PrinterInterface
    {
        return Printer::readable();
    }

    public function getRunFacade(): RunFacade
    {
        return $this->getProvidedDependency(NreplProvider::FACADE_RUN);
    }

    public function getApiFacade(): ApiFacade
    {
        return $this->getProvidedDependency(NreplProvider::FACADE_API);
    }
}
