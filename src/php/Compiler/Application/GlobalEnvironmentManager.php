<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentManagerInterface;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentRegistry;
use Phel\Compiler\Domain\Evaluator\RequireEvaluator;
use Phel\Lang\Registry;
use Phel\Shared\Munge;

use function in_array;

/**
 * @internal
 */
final class GlobalEnvironmentManager implements GlobalEnvironmentManagerInterface
{
    public function initialize(): GlobalEnvironmentInterface
    {
        $existing = GlobalEnvironmentRegistry::get();
        if ($existing instanceof GlobalEnvironmentInterface) {
            return $existing;
        }

        return $this->initializeNew();
    }

    /**
     * @param list<string> $retainedNamespaces
     */
    public function forgetProjectNamespaces(array $retainedNamespaces): void
    {
        $environment = $this->getInstance();
        $registry = Registry::getInstance();
        $snapshot = $registry->snapshot();
        $namespaces = array_keys($environment->snapshot()['definitions']);
        foreach ($namespaces as $namespace) {
            if (in_array($namespace, $retainedNamespaces, true)) {
                continue;
            }

            $environment->forgetNamespace($namespace);
        }

        $retainedRegistryKeys = array_map(new Munge()->encodeRegistryKey(...), $retainedNamespaces);
        foreach (array_keys($snapshot['definitions']) as $namespace) {
            if (!in_array($namespace, $retainedRegistryKeys, true)) {
                unset($snapshot['definitions'][$namespace], $snapshot['definitionsMetaData'][$namespace]);
            }
        }

        $registry->restore($snapshot);
        RequireEvaluator::clearCache();
    }

    public function reset(): void
    {
        GlobalEnvironmentRegistry::set(null);
    }

    public function isInitialized(): bool
    {
        return GlobalEnvironmentRegistry::has();
    }

    public function getInstance(): GlobalEnvironmentInterface
    {
        return GlobalEnvironmentRegistry::getOrCreate();
    }

    public function initializeNew(): GlobalEnvironmentInterface
    {
        Phel::clear();
        RequireEvaluator::clearCache();

        return GlobalEnvironmentRegistry::createFresh();
    }

    public function setInstance(GlobalEnvironmentInterface $env): void
    {
        GlobalEnvironmentRegistry::set($env);
    }
}
