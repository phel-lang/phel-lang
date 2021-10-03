<?php

declare(strict_types=1);

namespace Phel\Build\Extractor;

use Phel\Compiler\Analyzer\Ast\NsNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Lang\Symbol;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use RuntimeException;

final class NamespaceExtractor implements NamespaceExtractorInterface
{
    private CompilerFacadeInterface $compilerFacade;
    private NamespaceSorterInterface $namespaceSorter;

    public function __construct(
        CompilerFacadeInterface $compilerFacade,
        NamespaceSorterInterface $namespaceSorter
    ) {
        $this->compilerFacade = $compilerFacade;
        $this->namespaceSorter = $namespaceSorter;
    }

    /**
     * @throws ExtractorException
     * @throws LexerValueException
     */
    public function getNamespaceFromFile(string $path): NamespaceInformation
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
            $node = $this->compilerFacade->analyze($ast, NodeEnvironment::empty());

            if ($node instanceof NsNode) {
                return new NamespaceInformation(
                    realpath($path),
                    $node->getNamespace(),
                    array_map(
                        fn (Symbol $s) => $s->getFullName(),
                        $node->getRequireNs()
                    )
                );
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
     *
     * @return list<NamespaceInformation>
     */
    public function getNamespacesFromDirectories(array $directories): array
    {
        /** @var list<list<NamespaceInformation>> $namespaces */
        $namespaces = [];
        foreach ($directories as $directory) {
            $allNamespacesInDir = $this->findAllNs($directory);
            $namespaces[] = $allNamespacesInDir;
        }

        // Combine all nested namespaces and check for duplicates
        /** @var list<NamespaceInformation> $result */
        $result = [];
        /** @var array<string, NamespaceInformation> $seen */
        $seen = [];
        foreach ($namespaces as $namespaceInformationList) {
            foreach ($namespaceInformationList as $info) {
                if (isset($seen[$info->getNamespace()])) {
                    $firstFile = $seen[$info->getNamespace()]->getFile();
                    $secondFile = $info->getFile();
                    $namespace = $info->getNamespace();
                    throw ExtractorException::duplicateNamespace($namespace, $firstFile, $secondFile);
                }

                $result[] = $info;
                $seen[$info->getNamespace()] = $info;
            }
        }

        return $this->sortNamespaceInformationList($result);
    }

    /**
     * @param list<NamespaceInformation> $namespaceInformationList
     *
     * @return list<NamespaceInformation>
     */
    private function sortNamespaceInformationList(array $namespaceInformationList): array
    {
        $dependencyIndex = [];
        $infoIndex = [];
        foreach ($namespaceInformationList as $info) {
            $dependencyIndex[$info->getNamespace()] = $info->getDependencies();
            $infoIndex[$info->getNamespace()] = $info;
        }

        $orderedNamespaces = $this->namespaceSorter->sort(array_keys($dependencyIndex), $dependencyIndex);

        $result = [];
        foreach ($orderedNamespaces as $namespace) {
            if (isset($infoIndex[$namespace])) {
                $result[] = $infoIndex[$namespace];
            }
        }

        return $result;
    }

    /**
     * @throws ExtractorException
     */
    private function findAllNs(string $directory): array
    {
        $realpath = realpath($directory);
        if (!$realpath) {
            throw new RuntimeException("Directory '$directory' not found");
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($realpath));
        $phelIterator = new RegexIterator($iterator, '/^.+\.phel$/i', RecursiveRegexIterator::GET_MATCH);

        return array_map(
            fn ($file) => $this->getNamespaceFromFile($file[0]),
            iterator_to_array($phelIterator)
        );
    }
}
