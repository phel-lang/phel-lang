<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Lint\Domain\FileAnalysis;
use PHPUnit\Framework\TestCase;

use function count;

abstract class RuleTestCase extends TestCase
{
    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }

    protected function compilerFacade(): CompilerFacade
    {
        return new CompilerFacade();
    }

    protected function buildAnalysis(string $source, string $uri = 'test.phel'): FileAnalysis
    {
        $compiler = $this->compilerFacade();
        $forms = [];
        $namespace = '';

        $tokenStream = $compiler->lexString($source, $uri);
        while (true) {
            $parseTree = $compiler->parseNext($tokenStream);
            if (!$parseTree instanceof NodeInterface) {
                break;
            }

            if ($parseTree instanceof TriviaNodeInterface) {
                continue;
            }

            $readerResult = $compiler->read($parseTree);
            $form = $readerResult->getAst();

            if ($namespace === '') {
                $maybe = $this->extractNamespace($form);
                if ($maybe !== '') {
                    $namespace = $maybe;
                }
            }

            $forms[] = $form;
        }

        return new FileAnalysis(
            uri: $uri,
            namespace: $namespace,
            source: $source,
            forms: $forms,
            projectIndex: new ProjectIndex([], []),
        );
    }

    /**
     * @param bool|float|int|string|TypeInterface|null $form
     */
    private function extractNamespace(mixed $form): string
    {
        if (!$form instanceof PersistentListInterface || count($form) < 2) {
            return '';
        }

        $head = $form->get(0);
        if (!$head instanceof Symbol || $head->getName() !== Symbol::NAME_NS) {
            return '';
        }

        $name = $form->get(1);
        if (!$name instanceof Symbol) {
            return '';
        }

        return $name->getFullName();
    }
}
