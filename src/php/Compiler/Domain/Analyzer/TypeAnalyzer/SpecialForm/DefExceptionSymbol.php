<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\DefExceptionNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;

final class DefExceptionSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DefExceptionNode
    {
        if (count($list) !== 2) {
            throw AnalyzerException::withLocation("Exact one argument is required for 'defexception", $list);
        }

        $name = $list->get(1);
        if (!$name instanceof Symbol) {
            throw AnalyzerException::withLocation("First argument of 'defexception must be a Symbol.", $list);
        }

        $parent = new PhpClassNameNode(
            $env,
            Symbol::create('\\Exception'),
            $list->getStartLocation(),
        );

        return new DefExceptionNode(
            $env,
            $this->analyzer->getNamespace(),
            $name,
            $parent,
            $list->getStartLocation(),
        );
    }
}
