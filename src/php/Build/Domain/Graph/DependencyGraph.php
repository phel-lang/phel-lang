<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Graph;

use Phel\Build\Domain\Extractor\NamespaceInformation;

final readonly class DependencyGraph
{
    /**
     * @param array<string, GraphNode> $nodes            namespace => GraphNode
     * @param list<string>             $topologicalOrder sorted namespace list
     */
    public function __construct(
        public array $nodes,
        public array $topologicalOrder,
    ) {
    }

    public function getNode(string $namespace): ?GraphNode
    {
        return $this->nodes[$namespace] ?? null;
    }

    public function hasNode(string $namespace): bool
    {
        return isset($this->nodes[$namespace]);
    }

    /**
     * @return array<string, GraphNode>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return list<string>
     */
    public function getTopologicalOrder(): array
    {
        return $this->topologicalOrder;
    }

    /**
     * @return list<NamespaceInformation>
     */
    public function toNamespaceInformationList(): array
    {
        $result = [];
        foreach ($this->topologicalOrder as $namespace) {
            $node = $this->nodes[$namespace] ?? null;
            if ($node !== null) {
                $result[] = $node->toNamespaceInformation();
            }
        }

        return $result;
    }

    /**
     * @return array{nodes: array<string, array{file: string, namespace: string, mtime: int, dependencies: list<string>}>, topological_order: list<string>}
     */
    public function toArray(): array
    {
        $nodes = [];
        foreach ($this->nodes as $namespace => $node) {
            $nodes[$namespace] = $node->toArray();
        }

        return [
            'nodes' => $nodes,
            'topological_order' => $this->topologicalOrder,
        ];
    }

    /**
     * @param array{nodes: array<string, array{file: string, namespace: string, mtime: int, dependencies: list<string>}>, topological_order: list<string>} $data
     */
    public static function fromArray(array $data): self
    {
        $nodes = [];
        foreach ($data['nodes'] as $namespace => $nodeData) {
            $nodes[$namespace] = GraphNode::fromArray($nodeData);
        }

        return new self($nodes, $data['topological_order']);
    }
}
