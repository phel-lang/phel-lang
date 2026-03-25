<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Lang\Symbol;

/**
 * Serializes and restores GlobalEnvironment state (refers, aliases)
 * for a given namespace to/from plain arrays suitable for caching.
 */
final readonly class NamespaceEnvironmentSerializer
{
    public function __construct(
        private GlobalEnvironmentInterface $globalEnvironment,
    ) {}

    /**
     * Captures the current GlobalEnvironment state for a namespace
     * as a serializable plain-array structure.
     *
     * @return array{
     *     refers: array<string, array{ns: ?string, name: string}>,
     *     require_aliases: array<string, array{ns: ?string, name: string}>,
     *     use_aliases: array<string, array{ns: ?string, name: string}>,
     * }
     */
    public function capture(string $namespace): array
    {
        $toArray = static fn(array $symbols): array => array_map(
            static fn(Symbol $symbol): array => [
                'ns' => $symbol->getNamespace(),
                'name' => $symbol->getName(),
            ],
            $symbols,
        );

        return [
            'refers' => $toArray($this->globalEnvironment->getRefers($namespace)),
            'require_aliases' => $toArray($this->globalEnvironment->getRequireAliases($namespace)),
            'use_aliases' => $toArray($this->globalEnvironment->getUseAliases($namespace)),
        ];
    }

    /**
     * Restores GlobalEnvironment state for a namespace from
     * previously serialized environment data.
     *
     * @param array{
     *     refers: array<string, array{ns: ?string, name: string}>,
     *     require_aliases: array<string, array{ns: ?string, name: string}>,
     *     use_aliases: array<string, array{ns: ?string, name: string}>,
     * } $envData
     */
    public function restore(string $namespace, array $envData): void
    {
        foreach ($envData['refers'] as $key => $item) {
            $this->globalEnvironment->addRefer(
                $namespace,
                Symbol::createForNamespace(null, $key),
                Symbol::createForNamespace($item['ns'], $item['name']),
            );
        }

        foreach ($envData['require_aliases'] as $key => $item) {
            $this->globalEnvironment->addRequireAlias(
                $namespace,
                Symbol::createForNamespace(null, $key),
                Symbol::createForNamespace($item['ns'], $item['name']),
            );
        }

        foreach ($envData['use_aliases'] as $key => $item) {
            $this->globalEnvironment->addUseAlias(
                $namespace,
                Symbol::createForNamespace(null, $key),
                Symbol::createForNamespace($item['ns'], $item['name']),
            );
        }
    }
}
