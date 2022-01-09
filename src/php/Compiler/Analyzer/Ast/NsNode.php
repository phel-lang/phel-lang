<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Ast;

use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

final class NsNode extends AbstractNode
{
    /** @var Symbol[] */
    private array $requireNs;

    /** @var string[] */
    private array $requireFiles;

    private string $namespace;

    /**
     * @param list<Symbol> $requireNs
     * @param list<string> $requireFiles
     */
    public function __construct(string $namespace, array $requireNs, array $requireFiles, ?SourceLocation $sourceLocation = null)
    {
        parent::__construct(NodeEnvironment::empty(), $sourceLocation);
        $this->requireNs = $requireNs;
        if ($namespace !== 'phel\\core') {
            // All other files implicitly depend on phel\core
            $this->requireNs = [Symbol::create('phel\\core'), ...$requireNs];
        }
        $this->requireFiles = $requireFiles;
        $this->namespace = $namespace;
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
