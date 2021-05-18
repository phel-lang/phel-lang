<?php

declare(strict_types=1);

namespace Phel\Runtime\Extractor;

use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

final class NamespaceExtractor implements NamespaceExtractorInterface
{
    private string $rootDir;
    private CompilerFacadeInterface $compilerFacade;

    public function __construct(
        string $rootDir,
        CompilerFacadeInterface $compilerFacade
    ) {
        $this->rootDir = $rootDir;
        $this->compilerFacade = $compilerFacade;
    }

    /**
     * @throws ExtractorException
     * @throws LexerValueException
     */
    public function getNamespaceFromFile(string $path): string
    {
        $content = file_get_contents($path);

        try {
            $tokenStream = $this->compilerFacade->lexString($content);
            do {
                $parseTree = $this->compilerFacade->parseNext($tokenStream);
            } while ($parseTree && $parseTree instanceof TriviaNodeInterface);

            if (!$parseTree) {
                throw ExtractorException::cannotReadFile($path);
            }

            $readerResult = $this->compilerFacade->read($parseTree);
            $ast = $readerResult->getAst();

            if ($ast instanceof PersistentListInterface
                && $ast[0] instanceof Symbol
                && $ast[1] instanceof Symbol
                && $ast[0]->getName() === Symbol::NAME_NS
            ) {
                return $ast[1]->getName();
            }

            throw ExtractorException::cannotExtractNamespaceFromPath($path);
        } catch (AbstractParserException|ReaderException $e) {
            throw ExtractorException::cannotParseFile($path);
        }
    }

    /**
     * @param list<string> $directories
     *
     * @throws ExtractorException
     */
    public function getNamespacesFromDirectories(array $directories): array
    {
        $namespaces = [];
        foreach ($directories as $directory) {
            $allNamespacesInDir = $this->findAllNs($this->rootDir . '/' . $directory);
            $namespaces[] = $allNamespacesInDir;
        }

        return array_values(array_merge(...array_values($namespaces)));
    }

    /**
     * @throws ExtractorException
     */
    private function findAllNs(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $phelIterator = new RegexIterator($iterator, '/^.+\.phel$/i', RecursiveRegexIterator::GET_MATCH);

        return array_map(
            fn ($file) => $this->getNamespaceFromFile($file[0]),
            iterator_to_array($phelIterator)
        );
    }
}
