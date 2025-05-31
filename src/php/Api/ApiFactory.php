<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractFactory;
use Phel\Api\Application\PhelFnNormalizer;
use Phel\Api\Application\ReplCompleter;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Domain\ReplCompleterInterface;
use Phel\Api\Infrastructure\PhelFnLoader;

/**
 * @method ApiConfig getConfig()
 */
final class ApiFactory extends AbstractFactory
{
    public function createReplCompleter(): ReplCompleterInterface
    {
        return new ReplCompleter(
            $this->createPhelFnLoader(),
            $this->getConfig()->allNamespaces(),
        );
    }

    public function createPhelFnNormalizer(): PhelFnNormalizerInterface
    {
        return new PhelFnNormalizer(
            $this->createPhelFnLoader(),
            $this->getConfig()->allNamespaces(),
        );
    }

    private function createPhelFnLoader(): PhelFnLoaderInterface
    {
        return new PhelFnLoader(
            $this->getProvidedDependency(ApiProvider::FACADE_RUN),
        );
    }
}
