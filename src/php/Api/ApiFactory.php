<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractFactory;
use Phel\Api\Domain\PhelFnNormalizer;
use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Infrastructure\PhelFnLoader;
use Phel\Api\Infrastructure\PhelFnLoaderInterface;

final class ApiFactory extends AbstractFactory
{
    public function createPhelFnNormalizer(): PhelFnNormalizerInterface
    {
        return new PhelFnNormalizer(
            $this->createPhelFnLoader(),
        );
    }

    private function createPhelFnLoader(): PhelFnLoaderInterface
    {
        return new PhelFnLoader(
            $this->getProvidedDependency(ApiDependencyProvider::FACADE_RUN),
        );
    }
}
