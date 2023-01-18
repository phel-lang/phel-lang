<?php

declare(strict_types=1);

namespace Phel\Internal;

use Gacela\Framework\AbstractFactory;
use Phel\Internal\Domain\PhelFnNormalizer;
use Phel\Internal\Domain\PhelFnNormalizerInterface;
use Phel\Internal\Infrastructure\PhelFnLoader;
use Phel\Internal\Infrastructure\PhelFnLoaderInterface;

final class InternalFactory extends AbstractFactory
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
            $this->getProvidedDependency(InternalDependencyProvider::FACADE_RUN),
        );
    }
}
