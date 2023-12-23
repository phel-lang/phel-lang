<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class NsNode extends AbstractNode
{
    /**
     * @param list<Symbol> $requireNs
     * @param list<string> $requireFiles
     */
    public function __construct(
        private readonly string $namespace,
        private array $requireNs,
        private readonly array $requireFiles,
        ?SourceLocation $sourceLocation = null,
    ) {
        parent::__construct(NodeEnvironment::empty(), $sourceLocation);
        if ($namespace !== 'phel\\core') {
            // All other files implicitly depend on phel\core
            $this->requireNs = [Symbol::create('phel\\core'), ...$requireNs];
        }
    }

    /**
     * @return list<Symbol>
     */
    public function getRequireNs(): array
    {
        return $this->requireNs;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return list<string>
     */
    public function getRequireFiles(): array
    {
        return $this->requireFiles;
    }
}
